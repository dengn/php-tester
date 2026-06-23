package mo.hibernate.entity.gen;

import jakarta.persistence.*;

@Entity
@Table(name = "h_gen_auto")
public class AutoEntity {
    @Id
    @GeneratedValue(strategy = GenerationType.AUTO)
    private Long id;
    @Column(length = 40)
    private String label;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getLabel() { return label; }
    public void setLabel(String l) { this.label = l; }
}
