package mo.harness;

/**
 * Thrown by a scenario body to indicate it should be recorded as SKIP rather
 * than PASS/FAIL (e.g. a precondition was not met).
 */
public class SkipException extends RuntimeException {
    public SkipException(String message) {
        super(message);
    }
}
