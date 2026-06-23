package mo.harness;

/** Outcome of running a scenario. */
public final class Result {
    public final String framework;
    public final String category;
    public final String name;
    public final String status;       // PASS | FAIL | SKIP
    public final long durationMs;
    public final String errorCode;    // numeric MO code, "BEHAVIOR", SQLState, or null
    public final String errorMessage; // truncated

    public Result(String framework, String category, String name, String status,
                  long durationMs, String errorCode, String errorMessage) {
        this.framework = framework;
        this.category = category;
        this.name = name;
        this.status = status;
        this.durationMs = durationMs;
        this.errorCode = errorCode;
        this.errorMessage = errorMessage;
    }
}
