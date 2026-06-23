package mo.hibernate;

import mo.harness.Assert;
import mo.harness.Config;
import mo.harness.Db;
import mo.harness.Runner;
import mo.hibernate.entity.AllTypes;
import org.hibernate.Session;
import org.hibernate.SessionFactory;
import org.hibernate.Transaction;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;
import java.util.concurrent.atomic.AtomicReference;
import java.util.function.BiConsumer;
import java.util.function.Function;

/**
 * Hibernate per-column-type mapping matrix. For each mapped property on
 * AllTypes: schema-gen (implicit), persist-with-value, roundtrip-read,
 * persist-null, and update. The AllTypes entity carries every mapping kind
 * (@Column precision/scale/length, @Lob, @Enumerated STRING/ORDINAL, @Convert,
 * temporal types, UUID, byte[]).
 */
public final class HibernateTypeMatrix {

    private record Prop(String name,
                        BiConsumer<AllTypes, Object> setter,
                        Function<AllTypes, Object> getter,
                        Object sample,
                        Object updated) {}

    private static final AtomicReference<SessionFactory> SF = new AtomicReference<>();

    private static SessionFactory sf(Config cfg, String db) {
        SessionFactory s = SF.get();
        if (s == null || !s.isOpen()) {
            // "create" once; reused across the type matrix (data uses fresh rows).
            s = Boot.sessionFactory(cfg, db, 0, "create", AllTypes.class);
            SF.set(s);
        }
        return s;
    }

    public static void register(Runner r, Db db, String fw, String dbName) {
        Config cfg = db.config();
        List<Prop> props = props();

        for (Prop p : props) {
            // persist with value
            r.register(fw, "type", "persist/" + p.name(), () -> txWork(sf(cfg, dbName), s -> {
                AllTypes e = new AllTypes();
                p.setter().accept(e, p.sample());
                s.persist(e);
                s.flush();
                Assert.notNull(e.getId(), "id after persist");
            }));
            // roundtrip read
            r.register(fw, "type", "roundtrip/" + p.name(), () -> txWork(sf(cfg, dbName), s -> {
                AllTypes e = new AllTypes();
                p.setter().accept(e, p.sample());
                s.persist(e);
                s.flush();
                Long id = e.getId();
                s.clear();
                AllTypes got = s.find(AllTypes.class, id);
                Assert.notNull(got, "entity found");
                Object v = p.getter().apply(got);
                Assert.notNull(v, "round-tripped " + p.name() + " not null");
            }));
            // persist null
            r.register(fw, "type", "null/" + p.name(), () -> txWork(sf(cfg, dbName), s -> {
                AllTypes e = new AllTypes();
                // leave property null
                s.persist(e);
                s.flush();
                Long id = e.getId();
                s.clear();
                AllTypes got = s.find(AllTypes.class, id);
                Assert.notNull(got, "entity with null property persisted");
            }));
            // update
            r.register(fw, "type", "update/" + p.name(), () -> txWork(sf(cfg, dbName), s -> {
                AllTypes e = new AllTypes();
                p.setter().accept(e, p.sample());
                s.persist(e);
                s.flush();
                p.setter().accept(e, p.updated());
                s.flush();
                Long id = e.getId();
                s.clear();
                AllTypes got = s.find(AllTypes.class, id);
                Assert.notNull(p.getter().apply(got), "updated value present");
            }));
            // JPQL projection of this property
            r.register(fw, "jpql_type", "project/" + p.name(), () -> txWork(sf(cfg, dbName), s -> {
                AllTypes e = new AllTypes();
                p.setter().accept(e, p.sample());
                s.persist(e);
                s.flush();
                List<?> res = s.createQuery(
                                "select e." + p.name() + " from AllTypes e where e.id = :id", Object.class)
                        .setParameter("id", e.getId())
                        .getResultList();
                Assert.isTrue(!res.isEmpty(), "JPQL projection returned a row for " + p.name());
            }));
            // JPQL where on this property
            r.register(fw, "jpql_type", "where_notnull/" + p.name(), () -> txWork(sf(cfg, dbName), s -> {
                AllTypes e = new AllTypes();
                p.setter().accept(e, p.sample());
                s.persist(e);
                s.flush();
                Long n = s.createQuery(
                                "select count(e) from AllTypes e where e." + p.name() + " is not null", Long.class)
                        .getSingleResult();
                Assert.isTrue(n >= 1, "JPQL where-not-null count for " + p.name());
            }));
            // Criteria projection of this property
            r.register(fw, "criteria_type", "select/" + p.name(), () -> txWork(sf(cfg, dbName), s -> {
                AllTypes e = new AllTypes();
                p.setter().accept(e, p.sample());
                s.persist(e);
                s.flush();
                var cb = s.getCriteriaBuilder();
                var cq = cb.createQuery(Object.class);
                var root = cq.from(AllTypes.class);
                cq.select(root.get(p.name())).where(cb.equal(root.get("id"), e.getId()));
                List<?> res = s.createQuery(cq).getResultList();
                Assert.isTrue(!res.isEmpty(), "Criteria projection for " + p.name());
            }));
            // order-by this property
            r.register(fw, "jpql_type", "orderby/" + p.name(), () -> txWork(sf(cfg, dbName), s -> {
                AllTypes e = new AllTypes();
                p.setter().accept(e, p.sample());
                s.persist(e);
                s.flush();
                List<?> res = s.createQuery(
                                "select e.id from AllTypes e order by e." + p.name() + " asc", Long.class)
                        .setMaxResults(10).getResultList();
                Assert.notNull(res, "JPQL order-by for " + p.name());
            }));
        }

        // close the matrix factory at the end
        r.register(fw, "type", "zz_close_typematrix_factory", () -> {
            SessionFactory s = SF.getAndSet(null);
            if (s != null && s.isOpen()) s.close();
        });
    }

    @SuppressWarnings("unchecked")
    private static List<Prop> props() {
        List<Prop> list = new ArrayList<>();
        list.add(new Prop("intCol", (e, v) -> e.setIntCol((Integer) v), AllTypes::getIntCol, 12345, 54321));
        list.add(new Prop("longCol", (e, v) -> e.setLongCol((Long) v), AllTypes::getLongCol, 9_000_000_000L, 1L));
        list.add(new Prop("shortCol", (e, v) -> e.setShortCol((Short) v), AllTypes::getShortCol, (short) 123, (short) 7));
        list.add(new Prop("byteCol", (e, v) -> e.setByteCol((Byte) v), AllTypes::getByteCol, (byte) 12, (byte) 1));
        list.add(new Prop("boolCol", (e, v) -> e.setBoolCol((Boolean) v), AllTypes::getBoolCol, Boolean.TRUE, Boolean.FALSE));
        list.add(new Prop("doubleCol", (e, v) -> e.setDoubleCol((Double) v), AllTypes::getDoubleCol, 3.14159, 2.71828));
        list.add(new Prop("decimalCol", (e, v) -> e.setDecimalCol((BigDecimal) v), AllTypes::getDecimalCol,
                new BigDecimal("12345.6789"), new BigDecimal("1.0000")));
        list.add(new Prop("varcharCol", (e, v) -> e.setVarcharCol((String) v), AllTypes::getVarcharCol, "hello", "world"));
        list.add(new Prop("textCol", (e, v) -> e.setTextCol((String) v), AllTypes::getTextCol, "long text body", "new body"));
        list.add(new Prop("binaryCol", (e, v) -> e.setBinaryCol((byte[]) v), AllTypes::getBinaryCol,
                new byte[]{1, 2, 3, 4}, new byte[]{9, 8, 7}));
        list.add(new Prop("dateCol", (e, v) -> e.setDateCol((LocalDate) v), AllTypes::getDateCol,
                LocalDate.of(2026, 6, 23), LocalDate.of(2000, 1, 1)));
        list.add(new Prop("timeCol", (e, v) -> e.setTimeCol((LocalTime) v), AllTypes::getTimeCol,
                LocalTime.of(11, 22, 33), LocalTime.of(1, 2, 3)));
        list.add(new Prop("datetimeCol", (e, v) -> e.setDatetimeCol((LocalDateTime) v), AllTypes::getDatetimeCol,
                LocalDateTime.of(2026, 6, 23, 11, 22, 33), LocalDateTime.of(2000, 1, 1, 0, 0, 0)));
        list.add(new Prop("enumStringCol", (e, v) -> e.setEnumStringCol((AllTypes.Color) v), AllTypes::getEnumStringCol,
                AllTypes.Color.GREEN, AllTypes.Color.BLUE));
        list.add(new Prop("enumOrdinalCol", (e, v) -> e.setEnumOrdinalCol((AllTypes.Color) v), AllTypes::getEnumOrdinalCol,
                AllTypes.Color.RED, AllTypes.Color.BLUE));
        list.add(new Prop("tagsCol", (e, v) -> e.setTagsCol((List<String>) v), AllTypes::getTagsCol,
                List.of("a", "b", "c"), List.of("x")));
        list.add(new Prop("uuidCol", (e, v) -> e.setUuidCol((UUID) v), AllTypes::getUuidCol,
                UUID.randomUUID(), UUID.randomUUID()));
        return list;
    }

    interface Work { void run(Session s) throws Exception; }

    static void txWork(SessionFactory sf, Work w) throws Exception {
        try (Session s = sf.openSession()) {
            Transaction tx = s.beginTransaction();
            try {
                w.run(s);
                tx.commit();
            } catch (Exception e) {
                if (tx.isActive()) { try { tx.rollback(); } catch (Exception ignore) {} }
                throw e;
            }
        }
    }
}
