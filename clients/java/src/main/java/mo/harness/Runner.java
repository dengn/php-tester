package mo.harness;

import java.util.ArrayList;
import java.util.List;
import java.util.function.Predicate;

/**
 * Registers scenarios and runs them sequentially, each isolated in try/catch.
 */
public final class Runner {
    private final List<Scenario> scenarios = new ArrayList<>();
    private final List<Result> results = new ArrayList<>();

    public void register(String framework, String category, String name, ScenarioBody body) {
        scenarios.add(new Scenario(framework, category, name, body));
    }

    public int registeredCount() {
        return scenarios.size();
    }

    public List<Scenario> scenarios() {
        return scenarios;
    }

    public List<Result> results() {
        return results;
    }

    /**
     * Run scenarios matching the filter. Returns number actually run.
     * progressEvery: print a heartbeat line every N scenarios (0 = silent).
     */
    public int run(Predicate<Scenario> filter, int progressEvery) {
        int run = 0;
        int total = (int) scenarios.stream().filter(filter).count();
        int pass = 0, fail = 0, skip = 0;
        for (Scenario s : scenarios) {
            if (!filter.test(s)) continue;
            run++;
            long start = System.nanoTime();
            String status;
            String code = null;
            String msg = null;
            try {
                s.body.run();
                status = "PASS";
                pass++;
            } catch (SkipException se) {
                status = "SKIP";
                code = "SKIP";
                msg = ErrorClassifier.truncate(se.getMessage());
                skip++;
            } catch (Throwable t) {
                status = "FAIL";
                ErrorClassifier.Classification c = ErrorClassifier.classify(t);
                code = c.code;
                msg = c.message;
                fail++;
            }
            long durMs = (System.nanoTime() - start) / 1_000_000L;
            results.add(new Result(s.framework, s.category, s.name, status, durMs, code, msg));

            if (progressEvery > 0 && run % progressEvery == 0) {
                System.out.printf("  [%d/%d] run  pass=%d fail=%d skip=%d  (last: %s/%s/%s -> %s)%n",
                        run, total, pass, fail, skip, s.framework, s.category, s.name, status);
            }
        }
        return run;
    }
}
