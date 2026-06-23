package mo.mybatis;

import mo.harness.Config;
import org.apache.ibatis.mapping.Environment;
import org.apache.ibatis.session.Configuration;
import org.apache.ibatis.session.SqlSessionFactory;
import org.apache.ibatis.session.SqlSessionFactoryBuilder;
import org.apache.ibatis.transaction.jdbc.JdbcTransactionFactory;

import javax.sql.DataSource;
import java.io.PrintWriter;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.logging.Logger;

/** Programmatic MyBatis bootstrap (no XML config). */
public final class MyBatisBoot {

    private MyBatisBoot() {}

    public static SqlSessionFactory build(Config cfg, String db, Class<?>... mappers) {
        DataSource ds = new SimpleDataSource(cfg.dbUrl(db), cfg.user, cfg.pass);
        JdbcTransactionFactory txf = new JdbcTransactionFactory();
        Environment env = new Environment("mo", txf, ds);
        Configuration conf = new Configuration(env);
        conf.setMapUnderscoreToCamelCase(true);
        for (Class<?> m : mappers) {
            conf.addMapper(m);
        }
        return new SqlSessionFactoryBuilder().build(conf);
    }

    /** A minimal DataSource backed by DriverManager (sufficient for the suite). */
    static final class SimpleDataSource implements DataSource {
        private final String url, user, pass;
        SimpleDataSource(String url, String user, String pass) {
            this.url = url; this.user = user; this.pass = pass;
        }
        @Override public Connection getConnection() throws SQLException {
            return DriverManager.getConnection(url, user, pass);
        }
        @Override public Connection getConnection(String u, String p) throws SQLException {
            return DriverManager.getConnection(url, u, p);
        }
        @Override public PrintWriter getLogWriter() { return null; }
        @Override public void setLogWriter(PrintWriter out) {}
        @Override public void setLoginTimeout(int seconds) {}
        @Override public int getLoginTimeout() { return 0; }
        @Override public Logger getParentLogger() { return Logger.getLogger("mybatis-ds"); }
        @Override public <T> T unwrap(Class<T> iface) { throw new UnsupportedOperationException(); }
        @Override public boolean isWrapperFor(Class<?> iface) { return false; }
    }
}
