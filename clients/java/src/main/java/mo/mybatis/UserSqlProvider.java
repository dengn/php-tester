package mo.mybatis;

import org.apache.ibatis.jdbc.SQL;

import java.util.Map;

/** Dynamic SQL provider demonstrating MyBatis SQL builder. */
public class UserSqlProvider {

    public String search(Map<String, Object> params) {
        return new SQL() {{
            SELECT("id, name, age");
            FROM("mb_users");
            if (params.get("name") != null) {
                WHERE("name LIKE #{name}");
            }
            if (params.get("minAge") != null) {
                WHERE("age >= #{minAge}");
            }
            ORDER_BY("id");
        }}.toString();
    }
}
