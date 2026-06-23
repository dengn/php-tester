package mo.springdata;

import jakarta.persistence.*;
import java.math.BigDecimal;

/** Spring Data JPA entity. */
@Entity
@Table(name = "sd_customer")
public class Customer {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(length = 80, nullable = false)
    private String name;

    @Column(length = 80)
    private String city;

    private Integer age;

    @Column(precision = 12, scale = 2)
    private BigDecimal balance;

    public Customer() {}
    public Customer(String name, String city, Integer age, BigDecimal balance) {
        this.name = name; this.city = city; this.age = age; this.balance = balance;
    }

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getCity() { return city; }
    public void setCity(String city) { this.city = city; }
    public Integer getAge() { return age; }
    public void setAge(Integer age) { this.age = age; }
    public BigDecimal getBalance() { return balance; }
    public void setBalance(BigDecimal balance) { this.balance = balance; }
}
