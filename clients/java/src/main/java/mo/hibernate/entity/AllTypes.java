package mo.hibernate.entity;

import jakarta.persistence.*;
import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.util.UUID;

/** An entity exercising a wide range of column type mappings. */
@Entity
@Table(name = "h_all_types")
public class AllTypes {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private Integer intCol;
    private Long longCol;
    private Short shortCol;
    private Byte byteCol;
    private Boolean boolCol;
    private Double doubleCol;

    @Column(precision = 18, scale = 4)
    private BigDecimal decimalCol;

    @Column(length = 255)
    private String varcharCol;

    @Column(columnDefinition = "TEXT")
    @Lob
    private String textCol;

    @Column(length = 16)
    private byte[] binaryCol;

    private LocalDate dateCol;
    private LocalTime timeCol;
    private LocalDateTime datetimeCol;

    @Enumerated(EnumType.STRING)
    private Color enumStringCol;

    @Enumerated(EnumType.ORDINAL)
    private Color enumOrdinalCol;

    @Convert(converter = CsvListConverter.class)
    @Column(length = 255)
    private java.util.List<String> tagsCol;

    @Column(length = 36)
    private UUID uuidCol;

    public enum Color { RED, GREEN, BLUE }

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public Integer getIntCol() { return intCol; }
    public void setIntCol(Integer v) { this.intCol = v; }
    public Long getLongCol() { return longCol; }
    public void setLongCol(Long v) { this.longCol = v; }
    public Short getShortCol() { return shortCol; }
    public void setShortCol(Short v) { this.shortCol = v; }
    public Byte getByteCol() { return byteCol; }
    public void setByteCol(Byte v) { this.byteCol = v; }
    public Boolean getBoolCol() { return boolCol; }
    public void setBoolCol(Boolean v) { this.boolCol = v; }
    public Double getDoubleCol() { return doubleCol; }
    public void setDoubleCol(Double v) { this.doubleCol = v; }
    public BigDecimal getDecimalCol() { return decimalCol; }
    public void setDecimalCol(BigDecimal v) { this.decimalCol = v; }
    public String getVarcharCol() { return varcharCol; }
    public void setVarcharCol(String v) { this.varcharCol = v; }
    public String getTextCol() { return textCol; }
    public void setTextCol(String v) { this.textCol = v; }
    public byte[] getBinaryCol() { return binaryCol; }
    public void setBinaryCol(byte[] v) { this.binaryCol = v; }
    public LocalDate getDateCol() { return dateCol; }
    public void setDateCol(LocalDate v) { this.dateCol = v; }
    public LocalTime getTimeCol() { return timeCol; }
    public void setTimeCol(LocalTime v) { this.timeCol = v; }
    public LocalDateTime getDatetimeCol() { return datetimeCol; }
    public void setDatetimeCol(LocalDateTime v) { this.datetimeCol = v; }
    public Color getEnumStringCol() { return enumStringCol; }
    public void setEnumStringCol(Color v) { this.enumStringCol = v; }
    public Color getEnumOrdinalCol() { return enumOrdinalCol; }
    public void setEnumOrdinalCol(Color v) { this.enumOrdinalCol = v; }
    public java.util.List<String> getTagsCol() { return tagsCol; }
    public void setTagsCol(java.util.List<String> v) { this.tagsCol = v; }
    public UUID getUuidCol() { return uuidCol; }
    public void setUuidCol(UUID v) { this.uuidCol = v; }
}
