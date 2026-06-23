package mo.springdata;

import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.JpaSpecificationExecutor;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

import java.math.BigDecimal;
import java.util.List;

/** Spring Data JPA repository: derived queries, @Query (JPQL + native), Pageable, Specifications. */
public interface CustomerRepository extends JpaRepository<Customer, Long>, JpaSpecificationExecutor<Customer> {

    // Derived query methods
    List<Customer> findByCity(String city);

    List<Customer> findByAgeGreaterThan(int age);

    List<Customer> findByCityAndAgeLessThan(String city, int age);

    List<Customer> findByNameLike(String pattern);

    List<Customer> findByCityOrderByAgeDesc(String city);

    long countByCity(String city);

    boolean existsByName(String name);

    List<Customer> findTop3ByOrderByBalanceDesc();

    List<Customer> findByBalanceBetween(BigDecimal lo, BigDecimal hi);

    // @Query JPQL
    @Query("select c from Customer c where c.age >= :minAge")
    List<Customer> jpqlByMinAge(@Param("minAge") int minAge);

    @Query("select c.city, count(c) from Customer c group by c.city")
    List<Object[]> jpqlCityCounts();

    @Query("select avg(c.age) from Customer c")
    Double jpqlAvgAge();

    // @Query native
    @Query(value = "select * from sd_customer where city = :city", nativeQuery = true)
    List<Customer> nativeByCity(@Param("city") String city);

    @Query(value = "select count(*) from sd_customer", nativeQuery = true)
    long nativeCount();

    // Pageable
    Page<Customer> findByCity(String city, Pageable pageable);
}
