package mo.harness;

/**
 * Connection configuration sourced from environment variables, with sensible
 * defaults pointing at the local MatrixOne instance.
 */
public final class Config {
    public final String host;
    public final int port;
    public final String user;
    public final String pass;

    private Config(String host, int port, String user, String pass) {
        this.host = host;
        this.port = port;
        this.user = user;
        this.pass = pass;
    }

    public static Config fromEnv() {
        String host = env("MO_HOST", "127.0.0.1");
        int port = Integer.parseInt(env("MO_PORT", "6001"));
        String user = env("MO_USER", "root");
        String pass = env("MO_PASS", "111");
        return new Config(host, port, user, pass);
    }

    private static String env(String key, String def) {
        String v = System.getenv(key);
        return (v == null || v.isBlank()) ? def : v;
    }

    /** Common JDBC connection parameters required by the suite. */
    public static final String PARAMS =
            "allowPublicKeyRetrieval=true&useSSL=false&allowMultiQueries=true"
            + "&useServerPrepStmts=false&characterEncoding=UTF-8&serverTimezone=UTC"
            + "&tinyInt1isBit=false";

    /** JDBC URL without a database (server-level). */
    public String serverUrl() {
        return "jdbc:mysql://" + host + ":" + port + "/?" + PARAMS;
    }

    /** JDBC URL pointing at a specific database namespace. */
    public String dbUrl(String db) {
        return "jdbc:mysql://" + host + ":" + port + "/" + db + "?" + PARAMS;
    }

    @Override
    public String toString() {
        return user + "@" + host + ":" + port;
    }
}
