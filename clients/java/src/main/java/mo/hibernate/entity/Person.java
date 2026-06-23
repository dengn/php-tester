package mo.hibernate.entity;

import jakarta.persistence.*;

/** A simple entity for CRUD / JPQL / Criteria / pagination / batch tests. */
@Entity
@Table(name = "h_person")
public class Person {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(length = 100, nullable = false)
    private String name;

    private Integer age;

    @Column(length = 80)
    private String city;

    private Double salary;

    public Person() {}
    public Person(String name, Integer age, String city, Double salary) {
        this.name = name; this.age = age; this.city = city; this.salary = salary;
    }

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String n) { this.name = n; }
    public Integer getAge() { return age; }
    public void setAge(Integer a) { this.age = a; }
    public String getCity() { return city; }
    public void setCity(String c) { this.city = c; }
    public Double getSalary() { return salary; }
    public void setSalary(Double s) { this.salary = s; }
}
