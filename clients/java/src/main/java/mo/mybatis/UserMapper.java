package mo.mybatis;

import org.apache.ibatis.annotations.*;
import org.apache.ibatis.type.JdbcType;

import java.util.List;
import java.util.Map;

/** Annotation-based MyBatis mapper exercising CRUD, dynamic SQL, result maps. */
public interface UserMapper {

    @Update("CREATE TABLE mb_users (" +
            "id BIGINT AUTO_INCREMENT PRIMARY KEY, " +
            "name VARCHAR(100), age INT, balance DECIMAL(12,2), active BOOLEAN, " +
            "birth_date DATE, created_at DATETIME, avatar BLOB, tags VARCHAR(255))")
    void createTable();

    @Update("DROP TABLE IF EXISTS mb_users")
    void dropTable();

    @Insert("INSERT INTO mb_users (name, age, balance, active, birth_date, created_at, avatar, tags) " +
            "VALUES (#{name}, #{age}, #{balance}, #{active}, #{birthDate}, #{createdAt}, " +
            "#{avatar,jdbcType=BLOB}, #{tags})")
    @Options(useGeneratedKeys = true, keyProperty = "id")
    int insert(UserRecord u);

    @Insert("INSERT INTO mb_users (name, age) VALUES (#{name}, #{age})")
    int insertMinimal(UserRecord u);

    @Select("SELECT id, name, age, balance, active, birth_date, created_at, avatar, tags FROM mb_users WHERE id = #{id}")
    UserRecord findById(long id);

    @Select("SELECT id, name, age, balance, active, birth_date, created_at FROM mb_users ORDER BY id")
    List<UserRecord> findAll();

    @Select("SELECT COUNT(*) FROM mb_users")
    long count();

    @Update("UPDATE mb_users SET age = #{age} WHERE id = #{id}")
    int updateAge(@Param("id") long id, @Param("age") int age);

    @Delete("DELETE FROM mb_users WHERE id = #{id}")
    int delete(long id);

    @Select("SELECT id, name, age FROM mb_users WHERE age > #{minAge}")
    @Results(id = "userResult", value = {
            @Result(property = "id", column = "id", id = true),
            @Result(property = "name", column = "name"),
            @Result(property = "age", column = "age")
    })
    List<UserRecord> findOlderThan(int minAge);

    @Select("SELECT name, age FROM mb_users")
    @MapKey("name")
    Map<String, Map<String, Object>> findAsMap();

    // Dynamic SQL via @SelectProvider
    @SelectProvider(type = UserSqlProvider.class, method = "search")
    List<UserRecord> search(@Param("name") String name, @Param("minAge") Integer minAge);
}
