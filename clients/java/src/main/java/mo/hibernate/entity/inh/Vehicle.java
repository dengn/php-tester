package mo.hibernate.entity.inh;

import jakarta.persistence.*;

/** JOINED inheritance root. */
@Entity
@Table(name = "h_vehicle_joined")
@Inheritance(strategy = InheritanceType.JOINED)
public abstract class Vehicle {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    @Column(length = 50)
    private String make;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getMake() { return make; }
    public void setMake(String m) { this.make = m; }
}
