package mo.hibernate;

import mo.harness.Config;
import org.hibernate.SessionFactory;
import org.hibernate.cfg.Configuration;

import java.util.Properties;

/** Programmatic Hibernate bootstrap helpers. */
public final class Boot {

    private Boot() {}

    /** Build a SessionFactory for a given set of annotated entity classes. */
    public static SessionFactory sessionFactory(Config cfg, String db, int batchSize,
                                                String hbm2ddl, Class<?>... entities) {
        Configuration c = new Configuration();
        Properties p = new Properties();
        p.put("hibernate.connection.driver_class", "com.mysql.cj.jdbc.Driver");
        p.put("hibernate.connection.url", cfg.dbUrl(db));
        p.put("hibernate.connection.username", cfg.user);
        p.put("hibernate.connection.password", cfg.pass);
        // MatrixOne speaks the MySQL 8 wire protocol.
        p.put("hibernate.dialect", "org.hibernate.dialect.MySQLDialect");
        p.put("hibernate.hbm2ddl.auto", hbm2ddl);
        p.put("hibernate.show_sql", "false");
        p.put("hibernate.format_sql", "false");
        p.put("hibernate.connection.autocommit", "false");
        // Disable second-level cache.
        p.put("hibernate.cache.use_second_level_cache", "false");
        p.put("hibernate.cache.use_query_cache", "false");
        if (batchSize > 0) {
            p.put("hibernate.jdbc.batch_size", String.valueOf(batchSize));
            p.put("hibernate.order_inserts", "true");
            p.put("hibernate.order_updates", "true");
        }
        // Keep a single connection per factory; we create/close factories per scenario group.
        p.put("hibernate.connection.pool_size", "2");
        c.setProperties(p);
        for (Class<?> e : entities) {
            c.addAnnotatedClass(e);
        }
        return c.buildSessionFactory();
    }
}
