package mo.hibernate.entity.gen;

import jakarta.persistence.*;

@Entity
@Table(name = "h_gen_table")
public class TableEntity {
    @Id
    @GeneratedValue(strategy = GenerationType.TABLE, generator = "tbl_gen")
    @TableGenerator(name = "tbl_gen", table = "h_id_gen", pkColumnName = "gen_name",
            valueColumnName = "gen_val", pkColumnValue = "table_entity", allocationSize = 1)
    private Long id;
    @Column(length = 40)
    private String label;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getLabel() { return label; }
    public void setLabel(String l) { this.label = l; }
}
