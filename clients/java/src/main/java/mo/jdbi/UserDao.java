package mo.jdbi;

import org.jdbi.v3.sqlobject.statement.SqlQuery;
import org.jdbi.v3.sqlobject.statement.SqlUpdate;
import org.jdbi.v3.sqlobject.customizer.Bind;

import java.util.List;

/** JDBI SQL Object DAO bound to a fixed 'jdbi_users' table. */
public interface UserDao {

    @SqlUpdate("INSERT INTO jdbi_users (id, name) VALUES (:id, :name)")
    void insert(@Bind("id") int id, @Bind("name") String name);

    @SqlQuery("SELECT name FROM jdbi_users WHERE id = :id")
    String findName(@Bind("id") int id);

    @SqlQuery("SELECT COUNT(*) FROM jdbi_users")
    int countAll();

    @SqlQuery("SELECT name FROM jdbi_users ORDER BY id")
    List<String> allNames();
}
