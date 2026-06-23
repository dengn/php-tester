package mo.harness;

/**
 * Thrown when a statement ran successfully but produced a result that differs
 * from MySQL semantics. These are classified with error_code "BEHAVIOR".
 */
public class BehaviorMismatch extends RuntimeException {
    public BehaviorMismatch(String message) {
        super(message);
    }

    public BehaviorMismatch(String label, Object expectedMySql, Object actualMatrixOne) {
        super(label + " | MySQL expects [" + expectedMySql + "] but MatrixOne returned [" + actualMatrixOne + "]");
    }
}
