package mo.hibernate.entity.inh;

import jakarta.persistence.Entity;
import jakarta.persistence.Table;

@Entity
@Table(name = "h_circle_tpc")
public class Circle extends Shape {
    private Double radius;
    public Double getRadius() { return radius; }
    public void setRadius(Double r) { this.radius = r; }
}
