package mo.hibernate.entity.inh;

import jakarta.persistence.*;

@Entity
@DiscriminatorValue("CARD")
public class CardPayment extends Payment {
    @Column(length = 20)
    private String cardLast4;
    public String getCardLast4() { return cardLast4; }
    public void setCardLast4(String s) { this.cardLast4 = s; }
}
