package mo.hibernate.entity.inh;

import jakarta.persistence.*;

@Entity
@DiscriminatorValue("CASH")
public class CashPayment extends Payment {
    @Column(length = 50)
    private String receivedBy;
    public String getReceivedBy() { return receivedBy; }
    public void setReceivedBy(String s) { this.receivedBy = s; }
}
