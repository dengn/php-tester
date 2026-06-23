package mo.hibernate.entity.gen;

import jakarta.persistence.*;

@Entity
@Table(name = "h_gen_seq")
public class SeqEntity {
    @Id
    @GeneratedValue(strategy = GenerationType.SEQUENCE, generator = "seq_gen")
    @SequenceGenerator(name = "seq_gen", sequenceName = "h_seq_one", allocationSize = 1)
    private Long id;
    @Column(length = 40)
    private String label;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getLabel() { return label; }
    public void setLabel(String l) { this.label = l; }
}
