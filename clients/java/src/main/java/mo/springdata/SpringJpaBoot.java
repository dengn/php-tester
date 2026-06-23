package mo.springdata;

import jakarta.persistence.EntityManagerFactory;
import jakarta.persistence.SharedCacheMode;
import jakarta.persistence.ValidationMode;
import jakarta.persistence.spi.ClassTransformer;
import jakarta.persistence.spi.PersistenceUnitInfo;
import jakarta.persistence.spi.PersistenceUnitTransactionType;
import mo.harness.Config;
import org.hibernate.jpa.HibernatePersistenceProvider;

import javax.sql.DataSource;
import java.net.URL;
import java.util.Collections;
import java.util.List;
import java.util.Properties;

/**
 * Builds a Hibernate-backed JPA EntityManagerFactory programmatically (no
 * persistence.xml, no Spring Boot) for use by Spring Data's JpaRepositoryFactory.
 */
public final class SpringJpaBoot {

    private SpringJpaBoot() {}

    public static EntityManagerFactory entityManagerFactory(Config cfg, String db) {
        Properties props = new Properties();
        props.put("jakarta.persistence.jdbc.driver", "com.mysql.cj.jdbc.Driver");
        props.put("jakarta.persistence.jdbc.url", cfg.dbUrl(db));
        props.put("jakarta.persistence.jdbc.user", cfg.user);
        props.put("jakarta.persistence.jdbc.password", cfg.pass);
        props.put("hibernate.dialect", "org.hibernate.dialect.MySQLDialect");
        props.put("hibernate.hbm2ddl.auto", "create");
        props.put("hibernate.show_sql", "false");
        props.put("hibernate.connection.autocommit", "false");
        props.put("hibernate.cache.use_second_level_cache", "false");
        props.put("hibernate.connection.pool_size", "2");

        PersistenceUnitInfo info = new SimplePersistenceUnitInfo(props);
        return new HibernatePersistenceProvider()
                .createContainerEntityManagerFactory(info, Collections.emptyMap());
    }

    /** Minimal PersistenceUnitInfo listing our managed classes explicitly. */
    private static final class SimplePersistenceUnitInfo implements PersistenceUnitInfo {
        private final Properties props;

        SimplePersistenceUnitInfo(Properties props) { this.props = props; }

        @Override public String getPersistenceUnitName() { return "mo-springdata-pu"; }
        @Override public String getPersistenceProviderClassName() {
            return HibernatePersistenceProvider.class.getName();
        }
        @Override public PersistenceUnitTransactionType getTransactionType() {
            return PersistenceUnitTransactionType.RESOURCE_LOCAL;
        }
        @Override public DataSource getJtaDataSource() { return null; }
        @Override public DataSource getNonJtaDataSource() { return null; }
        @Override public List<String> getMappingFileNames() { return Collections.emptyList(); }
        @Override public List<URL> getJarFileUrls() { return Collections.emptyList(); }
        @Override public URL getPersistenceUnitRootUrl() { return null; }
        @Override public List<String> getManagedClassNames() {
            return List.of(Customer.class.getName());
        }
        @Override public boolean excludeUnlistedClasses() { return true; }
        @Override public SharedCacheMode getSharedCacheMode() { return SharedCacheMode.NONE; }
        @Override public ValidationMode getValidationMode() { return ValidationMode.NONE; }
        @Override public Properties getProperties() { return props; }
        @Override public String getPersistenceXMLSchemaVersion() { return "3.0"; }
        @Override public ClassLoader getClassLoader() { return Thread.currentThread().getContextClassLoader(); }
        @Override public void addTransformer(ClassTransformer transformer) { }
        @Override public ClassLoader getNewTempClassLoader() { return null; }
    }
}
