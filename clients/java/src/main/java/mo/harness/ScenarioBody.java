package mo.harness;

/** A scenario body that may throw any exception (failure signal). */
@FunctionalInterface
public interface ScenarioBody {
    void run() throws Exception;
}
