package mo.hibernate.entity;

import jakarta.persistence.*;

@Entity
@Table(name = "h_profile")
public class Profile {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(length = 500)
    private String bio;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getBio() { return bio; }
    public void setBio(String b) { this.bio = b; }
}
