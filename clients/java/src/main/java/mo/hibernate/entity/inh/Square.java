package mo.hibernate.entity.inh;

import jakarta.persistence.Entity;
import jakarta.persistence.Table;

@Entity
@Table(name = "h_square_tpc")
public class Square extends Shape {
    private Double side;
    public Double getSide() { return side; }
    public void setSide(Double s) { this.side = s; }
}
