package mo.hibernate.entity;

import jakarta.persistence.*;
import java.math.BigDecimal;

@Entity
@Table(name = "h_book")
public class Book {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(length = 200)
    private String title;

    @Column(precision = 8, scale = 2)
    private BigDecimal price;

    private Integer pages;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "author_id")
    private Author author;

    @Version
    private Long version;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getTitle() { return title; }
    public void setTitle(String t) { this.title = t; }
    public BigDecimal getPrice() { return price; }
    public void setPrice(BigDecimal p) { this.price = p; }
    public Integer getPages() { return pages; }
    public void setPages(Integer p) { this.pages = p; }
    public Author getAuthor() { return author; }
    public void setAuthor(Author a) { this.author = a; }
    public Long getVersion() { return version; }
    public void setVersion(Long v) { this.version = v; }
}
