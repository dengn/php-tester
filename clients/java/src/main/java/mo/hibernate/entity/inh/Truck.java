package mo.hibernate.entity.inh;

import jakarta.persistence.Entity;
import jakarta.persistence.Table;

@Entity
@Table(name = "h_truck_joined")
public class Truck extends Vehicle {
    private Double capacityTons;
    public Double getCapacityTons() { return capacityTons; }
    public void setCapacityTons(Double c) { this.capacityTons = c; }
}
