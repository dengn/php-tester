package mo.harness;

/** A framework module that registers scenarios into the runner. */
public interface SuiteModule {
    /** Framework key, e.g. "jdbc", "hibernate", "mybatis", "jooq". */
    String framework();

    /** Database namespace for this framework. */
    String database();

    /** Register all scenarios. The DB has already been created. */
    void register(Runner runner, Db db);
}
