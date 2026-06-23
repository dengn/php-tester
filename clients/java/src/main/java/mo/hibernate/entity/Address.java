package mo.hibernate.entity;

import jakarta.persistence.Column;
import jakarta.persistence.Embeddable;

@Embeddable
public class Address {
    @Column(name = "addr_street", length = 120)
    private String street;
    @Column(name = "addr_city", length = 80)
    private String city;
    @Column(name = "addr_zip", length = 20)
    private String zip;

    public Address() {}
    public Address(String street, String city, String zip) {
        this.street = street; this.city = city; this.zip = zip;
    }
    public String getStreet() { return street; }
    public void setStreet(String s) { this.street = s; }
    public String getCity() { return city; }
    public void setCity(String c) { this.city = c; }
    public String getZip() { return zip; }
    public void setZip(String z) { this.zip = z; }
}
