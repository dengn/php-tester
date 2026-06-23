package mo.hibernate.entity.inh;

import jakarta.persistence.Entity;
import jakarta.persistence.Table;

@Entity
@Table(name = "h_car_joined")
public class Car extends Vehicle {
    private Integer doors;
    public Integer getDoors() { return doors; }
    public void setDoors(Integer d) { this.doors = d; }
}
