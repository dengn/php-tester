package mo.hibernate.entity.inh;

import jakarta.persistence.*;

/** TABLE_PER_CLASS inheritance root. */
@Entity
@Inheritance(strategy = InheritanceType.TABLE_PER_CLASS)
public abstract class Shape {
    @Id
    @GeneratedValue(strategy = GenerationType.TABLE)
    private Long id;
    @Column(length = 30)
    private String color;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getColor() { return color; }
    public void setColor(String c) { this.color = c; }
}
