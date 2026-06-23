package mo.hibernate.entity;

import jakarta.persistence.*;

@Entity
@Table(name = "h_tag")
public class Tag {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(length = 50)
    private String label;

    public Tag() {}
    public Tag(String label) { this.label = label; }

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getLabel() { return label; }
    public void setLabel(String l) { this.label = l; }
}
