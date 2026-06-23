package mo.mybatis;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;

/** A POJO mapped by MyBatis. */
public class UserRecord {
    private Long id;
    private String name;
    private Integer age;
    private BigDecimal balance;
    private Boolean active;
    private LocalDate birthDate;
    private LocalDateTime createdAt;
    private byte[] avatar;
    private String tags; // handled by a custom type handler in some tests

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public Integer getAge() { return age; }
    public void setAge(Integer age) { this.age = age; }
    public BigDecimal getBalance() { return balance; }
    public void setBalance(BigDecimal b) { this.balance = b; }
    public Boolean getActive() { return active; }
    public void setActive(Boolean a) { this.active = a; }
    public LocalDate getBirthDate() { return birthDate; }
    public void setBirthDate(LocalDate d) { this.birthDate = d; }
    public LocalDateTime getCreatedAt() { return createdAt; }
    public void setCreatedAt(LocalDateTime t) { this.createdAt = t; }
    public byte[] getAvatar() { return avatar; }
    public void setAvatar(byte[] a) { this.avatar = a; }
    public String getTags() { return tags; }
    public void setTags(String t) { this.tags = t; }
}
