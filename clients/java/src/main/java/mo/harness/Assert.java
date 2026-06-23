package mo.harness;

import java.util.Objects;

/** Lightweight assertions. AssertionError -> FAIL; BehaviorMismatch -> FAIL/BEHAVIOR. */
public final class Assert {
    private Assert() {}

    public static void isTrue(boolean cond, String msg) {
        if (!cond) throw new AssertionError(msg);
    }

    public static void eq(Object expected, Object actual, String msg) {
        if (!Objects.equals(expected, actual)) {
            throw new AssertionError(msg + " expected=[" + expected + "] actual=[" + actual + "]");
        }
    }

    /** A semantic check vs MySQL: if it does not hold, it is a BEHAVIOR mismatch. */
    public static void behavior(boolean mysqlEquivalent, String label, Object expectedMySql, Object actual) {
        if (!mysqlEquivalent) {
            throw new BehaviorMismatch(label, expectedMySql, actual);
        }
    }

    public static void notNull(Object o, String msg) {
        if (o == null) throw new AssertionError(msg);
    }
}
