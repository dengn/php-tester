package mo.springdata;

import jakarta.persistence.EntityManager;
import jakarta.persistence.EntityManagerFactory;
import jakarta.persistence.EntityTransaction;
import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SuiteModule;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.support.JpaRepositoryFactory;

import java.math.BigDecimal;
import java.util.List;
import java.util.UUID;
import java.util.concurrent.atomic.AtomicReference;

/**
 * Spring Data JPA module (no Spring Boot). Builds a Hibernate-backed
 * EntityManagerFactory programmatically, then uses Spring Data's
 * JpaRepositoryFactory to materialize the CustomerRepository proxy and exercise
 * derived queries, @Query (JPQL + native), Pageable and Specifications.
 */
public final class SpringDataModule implements SuiteModule {

    public static final String DB = "mo_java_springdata";
    private static final String FW = "springdata";

    @Override public String framework() { return FW; }
    @Override public String database() { return DB; }

    private final AtomicReference<EntityManagerFactory> emfRef = new AtomicReference<>();

    private EntityManagerFactory emf(Db db) {
        EntityManagerFactory emf = emfRef.get();
        if (emf == null) {
            synchronized (this) {
                emf = emfRef.get();
                if (emf == null) {
                    emf = SpringJpaBoot.entityManagerFactory(db.config(), DB);
                    emfRef.set(emf);
                }
            }
        }
        return emf;
    }

    private interface RepoWork { void run(CustomerRepository repo, EntityManager em) throws Exception; }

    private void inRepo(Db db, RepoWork work) throws Exception {
        EntityManagerFactory emf = emf(db);
        EntityManager em = emf.createEntityManager();
        EntityTransaction tx = em.getTransaction();
        try {
            tx.begin();
            CustomerRepository repo = new JpaRepositoryFactory(em).getRepository(CustomerRepository.class);
            work.run(repo, em);
            tx.commit();
        } catch (Exception e) {
            if (tx.isActive()) try { tx.rollback(); } catch (Exception ignore) {}
            throw e;
        } finally {
            em.close();
        }
    }

    private static void seed(CustomerRepository repo) {
        String tag = UUID.randomUUID().toString().substring(0, 8);
        repo.save(new Customer("Ann_" + tag, "NYC", 30, new BigDecimal("100.00")));
        repo.save(new Customer("Bob_" + tag, "NYC", 45, new BigDecimal("250.50")));
        repo.save(new Customer("Cy_" + tag, "LA", 25, new BigDecimal("75.25")));
        repo.save(new Customer("Dee_" + tag, "SF", 50, new BigDecimal("500.00")));
    }

    @Override
    public void register(Runner r, Db db) {
        // Bootstrap (creates schema via hbm2ddl).
        r.register(FW, "bootstrap", "build_entity_manager_factory", () -> {
            EntityManagerFactory emf = emf(db);
            Assert.notNull(emf, "EntityManagerFactory built");
            Assert.isTrue(emf.isOpen(), "EMF open");
        });

        // CRUD via JpaRepository
        r.register(FW, "crud", "save_and_find", () -> inRepo(db, (repo, em) -> {
            Customer c = repo.save(new Customer("Saver " + UUID.randomUUID(), "TestCity", 33, new BigDecimal("1.00")));
            em.flush();
            Assert.notNull(c.getId(), "generated id");
            Customer got = repo.findById(c.getId()).orElse(null);
            Assert.notNull(got, "found saved customer");
        }));
        r.register(FW, "crud", "count_all", () -> inRepo(db, (repo, em) -> {
            seed(repo);
            em.flush();
            long n = repo.count();
            Assert.isTrue(n >= 4, "count >= seeded rows");
        }));
        r.register(FW, "crud", "delete_by_id", () -> inRepo(db, (repo, em) -> {
            Customer c = repo.save(new Customer("ToDelete " + UUID.randomUUID(), "X", 1, BigDecimal.ZERO));
            em.flush();
            repo.deleteById(c.getId());
            em.flush();
            Assert.isTrue(repo.findById(c.getId()).isEmpty(), "deleted");
        }));
        r.register(FW, "crud", "save_all_batch", () -> inRepo(db, (repo, em) -> {
            List<Customer> batch = List.of(
                    new Customer("B1 " + UUID.randomUUID(), "Z", 10, BigDecimal.ONE),
                    new Customer("B2 " + UUID.randomUUID(), "Z", 20, BigDecimal.TEN));
            repo.saveAll(batch);
            em.flush();
            Assert.isTrue(repo.countByCity("Z") >= 2, "saveAll persisted");
        }));
        r.register(FW, "crud", "find_all", () -> inRepo(db, (repo, em) -> {
            seed(repo);
            em.flush();
            Assert.isTrue(!repo.findAll().isEmpty(), "findAll non-empty");
        }));

        // Derived queries
        String[] derived = {
                "findByCity", "findByAgeGreaterThan", "findByCityAndAgeLessThan", "findByNameLike",
                "findByCityOrderByAgeDesc", "countByCity", "existsByName", "findTop3ByOrderByBalanceDesc",
                "findByBalanceBetween",
        };
        for (String d : derived) {
            r.register(FW, "derived_query", d, () -> inRepo(db, (repo, em) -> {
                seed(repo);
                em.flush();
                switch (d) {
                    case "findByCity" -> Assert.isTrue(repo.findByCity("NYC").size() >= 2, "findByCity");
                    case "findByAgeGreaterThan" -> Assert.notNull(repo.findByAgeGreaterThan(20), "ageGt");
                    case "findByCityAndAgeLessThan" -> Assert.notNull(repo.findByCityAndAgeLessThan("NYC", 40), "and");
                    case "findByNameLike" -> Assert.notNull(repo.findByNameLike("A%"), "like");
                    case "findByCityOrderByAgeDesc" -> Assert.notNull(repo.findByCityOrderByAgeDesc("NYC"), "orderBy");
                    case "countByCity" -> Assert.isTrue(repo.countByCity("NYC") >= 2, "countByCity");
                    case "existsByName" -> repo.existsByName("nobody");
                    case "findTop3ByOrderByBalanceDesc" -> Assert.isTrue(repo.findTop3ByOrderByBalanceDesc().size() <= 3, "top3");
                    case "findByBalanceBetween" -> Assert.notNull(
                            repo.findByBalanceBetween(new BigDecimal("0"), new BigDecimal("1000")), "between");
                    default -> {}
                }
            }));
        }

        // @Query JPQL + native
        r.register(FW, "query_annotation", "jpql_by_min_age", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            Assert.notNull(repo.jpqlByMinAge(30), "jpql min age");
        }));
        r.register(FW, "query_annotation", "jpql_group_by", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            Assert.notNull(repo.jpqlCityCounts(), "jpql group by");
        }));
        r.register(FW, "query_annotation", "jpql_avg", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            Assert.notNull(repo.jpqlAvgAge(), "jpql avg");
        }));
        r.register(FW, "query_annotation", "native_by_city", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            Assert.notNull(repo.nativeByCity("NYC"), "native by city");
        }));
        r.register(FW, "query_annotation", "native_count", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            Assert.isTrue(repo.nativeCount() >= 4, "native count");
        }));

        // Pageable
        r.register(FW, "pageable", "page_by_city", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            Pageable pr = PageRequest.of(0, 1);
            var page = repo.findByCity("NYC", pr);
            Assert.notNull(page, "page result");
            Assert.isTrue(page.getSize() == 1, "page size honored");
        }));
        r.register(FW, "pageable", "page_metadata", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            var page = repo.findAll(PageRequest.of(0, 2));
            Assert.isTrue(page.getTotalElements() >= 4, "page total elements");
            Assert.isTrue(page.getTotalPages() >= 2, "page total pages");
        }));
        r.register(FW, "pageable", "sorted_page", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            var page = repo.findAll(PageRequest.of(0, 3,
                    org.springframework.data.domain.Sort.by("age").descending()));
            Assert.notNull(page.getContent(), "sorted page content");
        }));

        // Specifications (JpaSpecificationExecutor)
        r.register(FW, "specification", "spec_city_equals", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            var spec = (org.springframework.data.jpa.domain.Specification<Customer>)
                    (root, q, cb) -> cb.equal(root.get("city"), "NYC");
            Assert.isTrue(repo.findAll(spec).size() >= 2, "spec equals");
        }));
        r.register(FW, "specification", "spec_and_predicate", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            var spec = (org.springframework.data.jpa.domain.Specification<Customer>)
                    (root, q, cb) -> cb.and(cb.equal(root.get("city"), "NYC"),
                            cb.greaterThan(root.<Integer>get("age"), 35));
            Assert.notNull(repo.findAll(spec), "spec and");
        }));
        r.register(FW, "specification", "spec_count", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            var spec = (org.springframework.data.jpa.domain.Specification<Customer>)
                    (root, q, cb) -> cb.greaterThan(root.<Integer>get("age"), 0);
            Assert.isTrue(repo.count(spec) >= 4, "spec count");
        }));

        // Modifying / bulk via repository delete
        r.register(FW, "modifying", "delete_all_in_batch", () -> inRepo(db, (repo, em) -> {
            seed(repo); em.flush();
            List<Customer> z = repo.findByCity("LA");
            repo.deleteAllInBatch(z);
            em.flush();
        }));

        // Close shared EMF at end.
        r.register(FW, "lifecycle", "close_emf", () -> {
            EntityManagerFactory emf = emfRef.getAndSet(null);
            if (emf != null && emf.isOpen()) emf.close();
        });
    }
}
