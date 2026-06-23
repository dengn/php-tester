package mo.hibernate;

import jakarta.persistence.criteria.CriteriaBuilder;
import jakarta.persistence.criteria.CriteriaQuery;
import jakarta.persistence.criteria.Root;
import mo.harness.Assert;
import mo.harness.BehaviorMismatch;
import mo.harness.Config;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;
import mo.hibernate.entity.*;
import mo.hibernate.entity.gen.AutoEntity;
import mo.hibernate.entity.gen.SeqEntity;
import mo.hibernate.entity.gen.TableEntity;
import mo.hibernate.entity.inh.*;
import org.hibernate.Session;
import org.hibernate.SessionFactory;
import org.hibernate.Transaction;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.util.List;
import java.util.UUID;
import java.util.concurrent.atomic.AtomicReference;

/**
 * Hibernate ORM 6 / JPA module. Uses programmatic bootstrap (Configuration ->
 * SessionFactory) with annotated entities and hibernate.hbm2ddl.auto=create.
 *
 * SessionFactories are shared across scenarios that use the same entity set
 * (built lazily on first use, closed at the end via a shutdown hook list).
 */
public final class HibernateModule implements SuiteModule {
    public static final String DB = "mo_java_hibernate";
    private static final String FW = "hibernate";

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    // Lazily-built shared factories.
    private final AtomicReference<SessionFactory> coreSf = new AtomicReference<>();

    private SessionFactory core(Config cfg) {
        SessionFactory sf = coreSf.get();
        if (sf == null) {
            synchronized (this) {
                sf = coreSf.get();
                if (sf == null) {
                    // Use "create" (not "update"): Hibernate's schema-update introspection
                    // issues an information_schema.tables GROUP BY query that MatrixOne
                    // rejects (covered as a dedicated finding in schemagen scenarios).
                    sf = Boot.sessionFactory(cfg, DB, 0, "create",
                            Person.class, Author.class, Book.class, Profile.class, Tag.class, Address.class);
                    coreSf.set(sf);
                }
            }
        }
        return sf;
    }

    @Override
    public void register(Runner r, Db db) {
        Config cfg = db.config();
        HibernateTypeMatrix.register(r, db, FW, DB);
        registerSchemaGen(r, cfg);
        registerCrud(r, cfg);
        registerJpql(r, cfg);
        registerCriteria(r, cfg);
        registerNative(r, cfg);
        registerRelationships(r, cfg);
        registerInheritance(r, cfg);
        registerTransactions(r, cfg);
        registerVersioning(r, cfg);
        registerPagination(r, cfg);
        registerBatch(r, cfg);
        registerGeneratedValue(r, cfg);
        registerEmbeddable(r, cfg);

        // Register a final pseudo-scenario to close shared factories.
        r.register(FW, "lifecycle", "close_shared_factories", () -> {
            SessionFactory sf = coreSf.getAndSet(null);
            if (sf != null && sf.isOpen()) sf.close();
        });
    }

    // ------------------------------------------------------ schema gen ----

    private void registerSchemaGen(Runner r, Config cfg) {
        r.register(FW, "schemagen", "create_all_entities", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create",
                    Person.class, Author.class, Book.class, Profile.class, Tag.class,
                    AllTypes.class)) {
                Assert.notNull(sf, "session factory built (hbm2ddl create succeeded)");
            }
        });
        r.register(FW, "schemagen", "create_inheritance_single_table", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create",
                    Payment.class, CardPayment.class, CashPayment.class)) {
                Assert.notNull(sf, "single-table schema created");
            }
        });
        r.register(FW, "schemagen", "create_inheritance_joined", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create",
                    Vehicle.class, Car.class, Truck.class)) {
                Assert.notNull(sf, "joined schema created");
            }
        });
        r.register(FW, "schemagen", "create_inheritance_table_per_class", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create",
                    Shape.class, Circle.class, Square.class)) {
                Assert.notNull(sf, "table-per-class schema created");
            }
        });
        r.register(FW, "schemagen", "create_alltypes_entity", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create", AllTypes.class)) {
                Assert.notNull(sf, "all-types schema created");
            }
        });
        // KNOWN finding: Hibernate hbm2ddl=update introspection issues an
        // information_schema.tables GROUP BY query that MatrixOne rejects
        // (ONLY_FULL_GROUP_BY style: "tables.TABLE_TYPE must appear in GROUP BY").
        r.register(FW, "schemagen", "hbm2ddl_update_introspection", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "update", Person.class)) {
                Assert.notNull(sf, "hbm2ddl=update succeeded");
            }
        });
        // KNOWN finding: hbm2ddl=validate also introspects schema.
        r.register(FW, "schemagen", "hbm2ddl_validate_introspection", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "validate", Person.class)) {
                Assert.notNull(sf, "hbm2ddl=validate succeeded");
            }
        });
    }

    // ------------------------------------------------------------ CRUD ----

    private void registerCrud(Runner r, Config cfg) {
        r.register(FW, "crud", "persist_find", () -> inTx(core(cfg), s -> {
            Person p = new Person("Alice " + UUID.randomUUID(), 30, "NYC", 50000.0);
            s.persist(p);
            s.flush();
            Long id = p.getId();
            Assert.notNull(id, "generated id");
            s.clear();
            Person got = s.find(Person.class, id);
            Assert.notNull(got, "found persisted entity");
            Assert.eq(p.getName(), got.getName(), "name round-trip");
        }));
        r.register(FW, "crud", "merge_update", () -> inTx(core(cfg), s -> {
            Person p = new Person("Bob " + UUID.randomUUID(), 40, "LA", 60000.0);
            s.persist(p);
            s.flush();
            s.clear();
            Person detached = new Person();
            detached.setId(p.getId());
            detached.setName(p.getName());
            detached.setAge(41);
            detached.setCity("SF");
            detached.setSalary(65000.0);
            s.merge(detached);
            s.flush();
            s.clear();
            Person got = s.find(Person.class, p.getId());
            Assert.eq(41, got.getAge(), "merged age");
        }));
        r.register(FW, "crud", "remove", () -> inTx(core(cfg), s -> {
            Person p = new Person("Carol " + UUID.randomUUID(), 25, "DC", 45000.0);
            s.persist(p);
            s.flush();
            Long id = p.getId();
            s.remove(p);
            s.flush();
            s.clear();
            Person got = s.find(Person.class, id);
            Assert.isTrue(got == null, "entity removed");
        }));
        r.register(FW, "crud", "flush_and_refresh", () -> inTx(core(cfg), s -> {
            Person p = new Person("Dave " + UUID.randomUUID(), 33, "BOS", 55000.0);
            s.persist(p);
            s.flush();
            s.refresh(p);
            Assert.notNull(p.getId(), "id after refresh");
        }));
        r.register(FW, "crud", "get_reference_lazy", () -> inTx(core(cfg), s -> {
            Person p = new Person("Eve " + UUID.randomUUID(), 28, "SEA", 52000.0);
            s.persist(p);
            s.flush();
            Person ref = s.getReference(Person.class, p.getId());
            Assert.eq(p.getId(), ref.getId(), "reference id");
        }));
        r.register(FW, "crud", "count_query", () -> inTx(core(cfg), s -> {
            for (int i = 0; i < 3; i++) {
                s.persist(new Person("Cnt " + UUID.randomUUID(), 20 + i, "X", 1000.0));
            }
            s.flush();
            Long n = s.createQuery("select count(p) from Person p", Long.class).getSingleResult();
            Assert.isTrue(n >= 3, "count >= 3");
        }));
    }

    // ------------------------------------------------------------ JPQL ----

    private void registerJpql(Runner r, Config cfg) {
        String[][] queries = {
                {"select_all", "select p from Person p"},
                {"where_eq", "select p from Person p where p.city = 'JPQL'"},
                {"where_gt", "select p from Person p where p.age > 25"},
                {"where_like", "select p from Person p where p.name like 'J%'"},
                {"where_in", "select p from Person p where p.age in (25, 30, 35)"},
                {"where_between", "select p from Person p where p.age between 20 and 40"},
                {"where_and_or", "select p from Person p where p.age > 20 and (p.city = 'JPQL' or p.city = 'Z')"},
                {"order_by", "select p from Person p order by p.age desc"},
                {"group_by", "select p.city, count(p) from Person p group by p.city"},
                {"having", "select p.city, count(p) from Person p group by p.city having count(p) > 0"},
                {"aggregate_avg", "select avg(p.age) from Person p"},
                {"aggregate_sum", "select sum(p.salary) from Person p"},
                {"aggregate_min_max", "select min(p.age), max(p.age) from Person p"},
                {"distinct", "select distinct p.city from Person p"},
                {"coalesce", "select coalesce(p.city, 'none') from Person p"},
                {"case_expr", "select case when p.age > 30 then 'old' else 'young' end from Person p"},
                {"concat_fn", "select concat(p.name, '!') from Person p"},
                {"length_fn", "select length(p.name) from Person p"},
                {"upper_lower", "select upper(p.name), lower(p.name) from Person p"},
                {"substring_fn", "select substring(p.name, 1, 2) from Person p"},
                {"subquery_in", "select p from Person p where p.age in (select max(p2.age) from Person p2)"},
                {"subquery_exists", "select p from Person p where exists (select 1 from Person p2 where p2.age = p.age)"},
                {"named_param", "select p from Person p where p.city = :city"},
                {"positional_param", "select p from Person p where p.age > ?1"},
                {"count_distinct", "select count(distinct p.city) from Person p"},
        };
        for (String[] q : queries) {
            r.register(FW, "jpql", q[0], () -> inTx(core(cfg), s -> {
                // ensure some data exists
                s.persist(new Person("JpqlSeed " + UUID.randomUUID(), 30, "JPQL", 1000.0));
                s.flush();
                var query = s.createQuery(q[1], Object.class);
                if (q[1].contains(":city")) query.setParameter("city", "JPQL");
                if (q[1].contains("?1")) query.setParameter(1, 10);
                query.setMaxResults(50);
                List<?> res = query.getResultList();
                Assert.notNull(res, "jpql result list");
            }));
        }
        r.register(FW, "jpql", "fetch_join", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("FetchAuthor " + UUID.randomUUID());
            Book b = new Book();
            b.setTitle("FetchBook");
            b.setPrice(new BigDecimal("9.99"));
            b.setPages(100);
            a.addBook(b);
            s.persist(a);
            s.flush();
            s.clear();
            List<Author> res = s.createQuery(
                    "select distinct a from Author a join fetch a.books where a.id = :id", Author.class)
                    .setParameter("id", a.getId())
                    .getResultList();
            Assert.isTrue(!res.isEmpty(), "fetch join returned author");
            Assert.isTrue(!res.get(0).getBooks().isEmpty(), "books eagerly fetched");
        }));
        r.register(FW, "jpql", "bulk_update", () -> inTx(core(cfg), s -> {
            s.persist(new Person("BulkU " + UUID.randomUUID(), 99, "BULK", 1.0));
            s.flush();
            int n = s.createMutationQuery("update Person p set p.salary = p.salary + 1 where p.city = 'BULK'")
                    .executeUpdate();
            Assert.isTrue(n >= 1, "bulk update affected rows");
        }));
        r.register(FW, "jpql", "bulk_delete", () -> inTx(core(cfg), s -> {
            s.persist(new Person("BulkD " + UUID.randomUUID(), 99, "BULKDEL", 1.0));
            s.flush();
            int n = s.createMutationQuery("delete from Person p where p.city = 'BULKDEL'").executeUpdate();
            Assert.isTrue(n >= 1, "bulk delete affected rows");
        }));
    }

    // -------------------------------------------------------- Criteria ----

    private void registerCriteria(Runner r, Config cfg) {
        r.register(FW, "criteria", "select_all", () -> inTx(core(cfg), s -> {
            CriteriaBuilder cb = s.getCriteriaBuilder();
            CriteriaQuery<Person> cq = cb.createQuery(Person.class);
            Root<Person> root = cq.from(Person.class);
            cq.select(root);
            List<Person> res = s.createQuery(cq).setMaxResults(10).getResultList();
            Assert.notNull(res, "criteria select all");
        }));
        r.register(FW, "criteria", "where_predicate", () -> inTx(core(cfg), s -> {
            s.persist(new Person("CritSeed " + UUID.randomUUID(), 50, "CRIT", 1.0));
            s.flush();
            CriteriaBuilder cb = s.getCriteriaBuilder();
            CriteriaQuery<Person> cq = cb.createQuery(Person.class);
            Root<Person> root = cq.from(Person.class);
            cq.select(root).where(cb.equal(root.get("city"), "CRIT"));
            List<Person> res = s.createQuery(cq).getResultList();
            Assert.isTrue(!res.isEmpty(), "criteria where matched");
        }));
        r.register(FW, "criteria", "order_by", () -> inTx(core(cfg), s -> {
            CriteriaBuilder cb = s.getCriteriaBuilder();
            CriteriaQuery<Person> cq = cb.createQuery(Person.class);
            Root<Person> root = cq.from(Person.class);
            cq.select(root).orderBy(cb.desc(root.get("age")));
            s.createQuery(cq).setMaxResults(5).getResultList();
        }));
        r.register(FW, "criteria", "aggregate_count", () -> inTx(core(cfg), s -> {
            CriteriaBuilder cb = s.getCriteriaBuilder();
            CriteriaQuery<Long> cq = cb.createQuery(Long.class);
            Root<Person> root = cq.from(Person.class);
            cq.select(cb.count(root));
            Long n = s.createQuery(cq).getSingleResult();
            Assert.notNull(n, "criteria count");
        }));
        r.register(FW, "criteria", "and_or_predicates", () -> inTx(core(cfg), s -> {
            CriteriaBuilder cb = s.getCriteriaBuilder();
            CriteriaQuery<Person> cq = cb.createQuery(Person.class);
            Root<Person> root = cq.from(Person.class);
            cq.select(root).where(cb.and(
                    cb.greaterThan(root.<Integer>get("age"), 10),
                    cb.or(cb.equal(root.get("city"), "A"), cb.equal(root.get("city"), "B"))));
            s.createQuery(cq).setMaxResults(10).getResultList();
        }));
        r.register(FW, "criteria", "like_predicate", () -> inTx(core(cfg), s -> {
            CriteriaBuilder cb = s.getCriteriaBuilder();
            CriteriaQuery<Person> cq = cb.createQuery(Person.class);
            Root<Person> root = cq.from(Person.class);
            cq.select(root).where(cb.like(root.get("name"), "A%"));
            s.createQuery(cq).setMaxResults(10).getResultList();
        }));
    }

    // ---------------------------------------------------------- Native ----

    private void registerNative(Runner r, Config cfg) {
        r.register(FW, "native", "native_select_scalar", () -> inTx(core(cfg), s -> {
            Object o = s.createNativeQuery("select 1 + 1", Integer.class).getSingleResult();
            Assert.eq(2, ((Number) o).intValue(), "native scalar");
        }));
        r.register(FW, "native", "native_select_entity", () -> inTx(core(cfg), s -> {
            s.persist(new Person("NativeSeed " + UUID.randomUUID(), 30, "NAT", 1.0));
            s.flush();
            List<Person> res = s.createNativeQuery("select * from h_person where city = 'NAT'", Person.class)
                    .setMaxResults(10).getResultList();
            Assert.isTrue(!res.isEmpty(), "native entity query");
        }));
        r.register(FW, "native", "native_update", () -> inTx(core(cfg), s -> {
            s.persist(new Person("NativeUpd " + UUID.randomUUID(), 30, "NATU", 1.0));
            s.flush();
            int n = s.createNativeMutationQuery("update h_person set salary = salary + 1 where city = 'NATU'")
                    .executeUpdate();
            Assert.isTrue(n >= 1, "native update");
        }));
        r.register(FW, "native", "native_function_now", () -> inTx(core(cfg), s -> {
            Object o = s.createNativeQuery("select now()", java.sql.Timestamp.class).getSingleResult();
            Assert.notNull(o, "native now()");
        }));
        r.register(FW, "native", "native_aggregate", () -> inTx(core(cfg), s -> {
            Object o = s.createNativeQuery("select count(*) from h_person", Long.class).getSingleResult();
            Assert.notNull(o, "native count");
        }));
    }

    // ---------------------------------------------------- Relationships ----

    private void registerRelationships(Runner r, Config cfg) {
        r.register(FW, "relationship", "one_to_many_cascade", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("OneToMany " + UUID.randomUUID());
            for (int i = 0; i < 3; i++) {
                Book b = new Book();
                b.setTitle("B" + i);
                b.setPrice(new BigDecimal("1.00"));
                b.setPages(10);
                a.addBook(b);
            }
            s.persist(a);
            s.flush();
            s.clear();
            Author got = s.find(Author.class, a.getId());
            Assert.eq(3, got.getBooks().size(), "cascaded books count");
        }));
        r.register(FW, "relationship", "many_to_one", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("ManyToOne " + UUID.randomUUID());
            s.persist(a);
            Book b = new Book();
            b.setTitle("M2O");
            b.setPrice(new BigDecimal("2.00"));
            b.setPages(20);
            b.setAuthor(a);
            s.persist(b);
            s.flush();
            s.clear();
            Book got = s.find(Book.class, b.getId());
            Assert.notNull(got.getAuthor(), "many-to-one author loaded");
        }));
        r.register(FW, "relationship", "one_to_one", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("OneToOne " + UUID.randomUUID());
            Profile pr = new Profile();
            pr.setBio("bio text");
            a.setProfile(pr);
            s.persist(a);
            s.flush();
            s.clear();
            Author got = s.find(Author.class, a.getId());
            Assert.notNull(got.getProfile(), "one-to-one profile loaded");
        }));
        r.register(FW, "relationship", "many_to_many_jointable", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("ManyToMany " + UUID.randomUUID());
            a.getTags().add(new Tag("t1"));
            a.getTags().add(new Tag("t2"));
            s.persist(a);
            s.flush();
            s.clear();
            Author got = s.find(Author.class, a.getId());
            Assert.eq(2, got.getTags().size(), "many-to-many tags count");
        }));
        r.register(FW, "relationship", "orphan_removal", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("Orphan " + UUID.randomUUID());
            Book b = new Book();
            b.setTitle("toRemove");
            b.setPrice(new BigDecimal("1.00"));
            b.setPages(1);
            a.addBook(b);
            s.persist(a);
            s.flush();
            a.getBooks().remove(0);
            s.flush();
            s.clear();
            Author got = s.find(Author.class, a.getId());
            Assert.eq(0, got.getBooks().size(), "orphan removed");
        }));
        r.register(FW, "relationship", "lazy_collection_load", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("Lazy " + UUID.randomUUID());
            Book b = new Book();
            b.setTitle("lazybook");
            b.setPrice(new BigDecimal("1.00"));
            b.setPages(1);
            a.addBook(b);
            s.persist(a);
            s.flush();
            s.clear();
            Author got = s.find(Author.class, a.getId());
            // Triggers lazy load within the session.
            Assert.eq(1, got.getBooks().size(), "lazy collection size");
        }));
        r.register(FW, "relationship", "element_collection", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("ElemColl " + UUID.randomUUID());
            a.getEmails().add("a@x.com");
            a.getEmails().add("b@x.com");
            s.persist(a);
            s.flush();
            s.clear();
            Author got = s.find(Author.class, a.getId());
            Assert.eq(2, got.getEmails().size(), "element collection size");
        }));
    }

    // ----------------------------------------------------- Inheritance ----

    private void registerInheritance(Runner r, Config cfg) {
        r.register(FW, "inheritance", "single_table_persist_query", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create",
                    Payment.class, CardPayment.class, CashPayment.class)) {
                inTx(sf, s -> {
                    CardPayment cp = new CardPayment();
                    cp.setAmount(99.0);
                    cp.setCardLast4("1234");
                    s.persist(cp);
                    CashPayment csh = new CashPayment();
                    csh.setAmount(10.0);
                    csh.setReceivedBy("clerk");
                    s.persist(csh);
                    s.flush();
                    s.clear();
                    List<Payment> all = s.createQuery("select p from Payment p", Payment.class).getResultList();
                    Assert.isTrue(all.size() >= 2, "single-table polymorphic query");
                });
            }
        });
        r.register(FW, "inheritance", "joined_persist_query", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create",
                    Vehicle.class, Car.class, Truck.class)) {
                inTx(sf, s -> {
                    Car car = new Car();
                    car.setMake("Toyota");
                    car.setDoors(4);
                    s.persist(car);
                    Truck tr = new Truck();
                    tr.setMake("Volvo");
                    tr.setCapacityTons(20.0);
                    s.persist(tr);
                    s.flush();
                    s.clear();
                    List<Vehicle> all = s.createQuery("select v from Vehicle v", Vehicle.class).getResultList();
                    Assert.isTrue(all.size() >= 2, "joined polymorphic query");
                });
            }
        });
        r.register(FW, "inheritance", "table_per_class_persist_query", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create",
                    Shape.class, Circle.class, Square.class)) {
                inTx(sf, s -> {
                    Circle ci = new Circle();
                    ci.setColor("red");
                    ci.setRadius(2.0);
                    s.persist(ci);
                    Square sq = new Square();
                    sq.setColor("blue");
                    sq.setSide(3.0);
                    s.persist(sq);
                    s.flush();
                    s.clear();
                    List<Shape> all = s.createQuery("select sh from Shape sh", Shape.class).getResultList();
                    Assert.isTrue(all.size() >= 2, "table-per-class polymorphic query");
                });
            }
        });
    }

    // ----------------------------------------------------- Transactions ----

    private void registerTransactions(Runner r, Config cfg) {
        r.register(FW, "transaction", "commit", () -> {
            SessionFactory sf = core(cfg);
            try (Session s = sf.openSession()) {
                Transaction tx = s.beginTransaction();
                Person p = new Person("TxCommit " + UUID.randomUUID(), 30, "TXC", 1.0);
                s.persist(p);
                tx.commit();
                try (Session s2 = sf.openSession()) {
                    Person got = s2.find(Person.class, p.getId());
                    Assert.notNull(got, "committed entity visible");
                }
            }
        });
        r.register(FW, "transaction", "rollback", () -> {
            SessionFactory sf = core(cfg);
            Long id;
            try (Session s = sf.openSession()) {
                Transaction tx = s.beginTransaction();
                Person p = new Person("TxRollback " + UUID.randomUUID(), 30, "TXR", 1.0);
                s.persist(p);
                s.flush();
                id = p.getId();
                tx.rollback();
            }
            try (Session s2 = sf.openSession()) {
                Person got = s2.find(Person.class, id);
                Assert.isTrue(got == null, "rolled-back entity not visible");
            }
        });
        r.register(FW, "transaction", "nested_savepoint_rollback", () -> {
            // KNOWN: ROLLBACK TO SAVEPOINT unimplemented. Hibernate uses JDBC savepoints
            // for Session-level nested behavior in some flows; here we drive savepoints
            // through the underlying JDBC connection to mirror nested-tx semantics.
            SessionFactory sf = core(cfg);
            try (Session s = sf.openSession()) {
                s.doWork(conn -> {
                    conn.setAutoCommit(false);
                    try (var st = conn.createStatement()) {
                        st.execute("INSERT INTO h_person (name, age, city, salary) VALUES ('Outer', 1, 'NEST', 1)");
                    }
                    var sp = conn.setSavepoint("inner");
                    try (var st = conn.createStatement()) {
                        st.execute("INSERT INTO h_person (name, age, city, salary) VALUES ('Inner', 2, 'NEST', 1)");
                    }
                    conn.rollback(sp); // expected to throw on MatrixOne
                    conn.commit();
                    conn.setAutoCommit(true);
                });
            }
        });
    }

    // ----------------------------------------------------- Versioning ----

    private void registerVersioning(Runner r, Config cfg) {
        r.register(FW, "versioning", "optimistic_version_increment", () -> inTx(core(cfg), s -> {
            Book b = new Book();
            b.setTitle("Versioned " + UUID.randomUUID());
            b.setPrice(new BigDecimal("1.00"));
            b.setPages(1);
            s.persist(b);
            s.flush();
            Long v0 = b.getVersion();
            b.setPages(2);
            s.flush();
            Long v1 = b.getVersion();
            Assert.isTrue(v1 != null && (v0 == null || v1 > v0), "version incremented");
        }));
        r.register(FW, "versioning", "optimistic_lock_conflict", () -> {
            SessionFactory sf = core(cfg);
            Long id;
            try (Session s = sf.openSession()) {
                Transaction tx = s.beginTransaction();
                Book b = new Book();
                b.setTitle("Conflict " + UUID.randomUUID());
                b.setPrice(new BigDecimal("1.00"));
                b.setPages(1);
                s.persist(b);
                tx.commit();
                id = b.getId();
            }
            // Two sessions load and update concurrently; second should conflict.
            try (Session s1 = sf.openSession(); Session s2 = sf.openSession()) {
                Transaction t1 = s1.beginTransaction();
                Book b1 = s1.find(Book.class, id);
                Transaction t2 = s2.beginTransaction();
                Book b2 = s2.find(Book.class, id);
                b1.setPages(10);
                t1.commit();
                b2.setPages(20);
                boolean conflict = false;
                try {
                    t2.commit();
                } catch (Exception e) {
                    conflict = true;
                }
                Assert.isTrue(conflict, "optimistic lock conflict detected");
            }
        });
    }

    // ----------------------------------------------------- Pagination ----

    private void registerPagination(Runner r, Config cfg) {
        r.register(FW, "pagination", "first_max_results", () -> inTx(core(cfg), s -> {
            for (int i = 0; i < 10; i++) {
                s.persist(new Person("Page " + UUID.randomUUID(), i, "PAGE", 1.0));
            }
            s.flush();
            List<Person> page = s.createQuery("select p from Person p where p.city='PAGE' order by p.id", Person.class)
                    .setFirstResult(2)
                    .setMaxResults(3)
                    .getResultList();
            Assert.eq(3, page.size(), "page size");
        }));
        r.register(FW, "pagination", "offset_pagination_native", () -> inTx(core(cfg), s -> {
            List<?> res = s.createNativeQuery("select id from h_person order by id limit 5 offset 2")
                    .getResultList();
            Assert.notNull(res, "native offset pagination");
        }));
    }

    // ---------------------------------------------------------- Batch ----

    private void registerBatch(Runner r, Config cfg) {
        int[] batches = {10, 50, 100};
        for (int bs : batches) {
            r.register(FW, "batch", "batched_inserts_" + bs, () -> {
                try (SessionFactory sf = Boot.sessionFactory(cfg, DB, bs, "create", Person.class)) {
                    try (Session s = sf.openSession()) {
                        Transaction tx = s.beginTransaction();
                        String tag = "BATCH" + bs + "_" + UUID.randomUUID();
                        for (int i = 0; i < bs * 2; i++) {
                            Person p = new Person("Batch " + i, i, tag, 1.0);
                            s.persist(p);
                            if (i % bs == 0) {
                                s.flush();
                                s.clear();
                            }
                        }
                        tx.commit();
                        try (Session s2 = sf.openSession()) {
                            Long n = s2.createQuery("select count(p) from Person p where p.city = :c", Long.class)
                                    .setParameter("c", tag).getSingleResult();
                            Assert.eq((long) bs * 2, n, "batched inserts count");
                        }
                    }
                }
            });
        }
    }

    // --------------------------------------------------- GeneratedValue ----

    private void registerGeneratedValue(Runner r, Config cfg) {
        r.register(FW, "generatedvalue", "identity", () -> inTx(core(cfg), s -> {
            Person p = new Person("Identity " + UUID.randomUUID(), 1, "GV", 1.0);
            s.persist(p);
            s.flush();
            Assert.notNull(p.getId(), "IDENTITY id generated");
        }));
        r.register(FW, "generatedvalue", "sequence", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create", SeqEntity.class)) {
                inTx(sf, s -> {
                    SeqEntity e = new SeqEntity();
                    e.setLabel("seq");
                    s.persist(e);
                    s.flush();
                    Assert.notNull(e.getId(), "SEQUENCE id generated");
                });
            }
        });
        r.register(FW, "generatedvalue", "table", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create", TableEntity.class)) {
                inTx(sf, s -> {
                    TableEntity e = new TableEntity();
                    e.setLabel("tbl");
                    s.persist(e);
                    s.flush();
                    Assert.notNull(e.getId(), "TABLE id generated");
                });
            }
        });
        r.register(FW, "generatedvalue", "auto", () -> {
            try (SessionFactory sf = Boot.sessionFactory(cfg, DB, 0, "create", AutoEntity.class)) {
                inTx(sf, s -> {
                    AutoEntity e = new AutoEntity();
                    e.setLabel("auto");
                    s.persist(e);
                    s.flush();
                    Assert.notNull(e.getId(), "AUTO id generated");
                });
            }
        });
    }

    // ----------------------------------------------------- Embeddable ----

    private void registerEmbeddable(Runner r, Config cfg) {
        r.register(FW, "embeddable", "embedded_address", () -> inTx(core(cfg), s -> {
            Author a = new Author();
            a.setName("Embed " + UUID.randomUUID());
            a.setAddress(new Address("123 Main", "Townsville", "12345"));
            s.persist(a);
            s.flush();
            s.clear();
            Author got = s.find(Author.class, a.getId());
            Assert.notNull(got.getAddress(), "embedded address loaded");
            Assert.eq("Townsville", got.getAddress().getCity(), "embedded city");
        }));
    }

    // ------------------------------------------------------------ utils ----

    interface SessionWork {
        void run(Session s) throws Exception;
    }

    static void inTx(SessionFactory sf, SessionWork work) throws Exception {
        try (Session s = sf.openSession()) {
            Transaction tx = s.beginTransaction();
            try {
                work.run(s);
                tx.commit();
            } catch (Exception e) {
                if (tx.isActive()) {
                    try { tx.rollback(); } catch (Exception ignore) {}
                }
                throw e;
            }
        }
    }
}
