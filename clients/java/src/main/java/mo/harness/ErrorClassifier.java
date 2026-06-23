package mo.harness;

import java.sql.SQLException;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Classifies a Throwable into a (errorCode, errorMessage) pair.
 *
 * MatrixOne surfaces its own numeric codes embedded in the SQLException message
 * (e.g. "... [20105] ... function not implemented"). We parse those, and fall
 * back to {@link SQLException#getErrorCode()} / {@link SQLException#getSQLState()}.
 */
public final class ErrorClassifier {

    /** Known MatrixOne / MySQL numeric signatures we surface explicitly. */
    private static final int[] KNOWN_CODES = {
            20105, // function / operator not implemented
            20101, // internal "not implemented yet" (e.g. ROLLBACK TO SAVEPOINT)
            20203, // stricter argument/type validation
            1690,  // out-of-range / overflow
            1062,  // duplicate entry for key
            1064,  // SQL parser / syntax error
            20102, 20103, 20104, // assorted MO internal codes
    };

    // Matches a bracketed or bare numeric code anywhere in the message.
    private static final Pattern CODE_IN_MSG =
            Pattern.compile("\\b(1064|1062|1690|20101|20102|20103|20104|20105|20203|20301)\\b");

    public static class Classification {
        public final String code;
        public final String message;
        Classification(String code, String message) {
            this.code = code;
            this.message = message;
        }
    }

    private ErrorClassifier() {}

    public static Classification classify(Throwable t) {
        if (t instanceof BehaviorMismatch) {
            return new Classification("BEHAVIOR", truncate(t.getMessage()));
        }
        // Unwrap common ORM wrappers to find a SQLException.
        SQLException sql = findSqlException(t);
        String rawMessage = deepestMessage(t);

        String code = null;
        if (sql != null) {
            int ec = sql.getErrorCode();
            String state = sql.getSQLState();
            // Prefer a code embedded in the message (MatrixOne native).
            String inMsg = extractCodeFromMessage(rawMessage);
            if (inMsg != null) {
                code = inMsg;
            } else if (ec != 0) {
                code = String.valueOf(ec);
            } else if (state != null && !state.isBlank()) {
                code = state;
            }
        } else {
            String inMsg = extractCodeFromMessage(rawMessage);
            if (inMsg != null) {
                code = inMsg;
            } else {
                // Pure harness/Java exception (NPE etc.) — surface class name.
                code = t.getClass().getSimpleName();
            }
        }
        return new Classification(code, truncate(rawMessage));
    }

    private static String extractCodeFromMessage(String msg) {
        if (msg == null) return null;
        Matcher m = CODE_IN_MSG.matcher(msg);
        if (m.find()) {
            return m.group(1);
        }
        return null;
    }

    private static SQLException findSqlException(Throwable t) {
        Throwable cur = t;
        int guard = 0;
        while (cur != null && guard++ < 30) {
            if (cur instanceof SQLException) {
                return (SQLException) cur;
            }
            cur = cur.getCause();
        }
        return null;
    }

    /** The most-specific (deepest cause) message, which usually carries the MO detail. */
    public static String deepestMessage(Throwable t) {
        Throwable cur = t;
        Throwable last = t;
        int guard = 0;
        while (cur != null && guard++ < 30) {
            last = cur;
            cur = cur.getCause();
        }
        String msg = last.getMessage();
        if (msg == null || msg.isBlank()) {
            // fall back to top-level
            msg = t.getMessage();
        }
        if (msg == null) {
            msg = t.getClass().getName();
        }
        return msg.replaceAll("\\s+", " ").trim();
    }

    public static String truncate(String s) {
        if (s == null) return null;
        s = s.replaceAll("\\s+", " ").trim();
        return s.length() <= 200 ? s : s.substring(0, 200) + "...";
    }

    public static boolean isKnownCode(String code) {
        if (code == null) return false;
        try {
            int c = Integer.parseInt(code);
            for (int k : KNOWN_CODES) if (k == c) return true;
        } catch (NumberFormatException ignore) {}
        return false;
    }
}
