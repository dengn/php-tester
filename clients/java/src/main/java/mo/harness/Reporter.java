package mo.harness;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.TreeMap;

/** Writes reports/results.json and reports/summary.md. */
public final class Reporter {

    private final String engine;
    private final List<Result> results;

    public Reporter(String engine, List<Result> results) {
        this.engine = engine;
        this.results = results;
    }

    public void write(Path dir) throws IOException {
        Files.createDirectories(dir);
        writeJson(dir.resolve("results.json"));
        writeMarkdown(dir.resolve("summary.md"));
    }

    private void writeJson(Path file) throws IOException {
        Map<String, Object> root = new LinkedHashMap<>();
        root.put("engine", engine);

        Map<String, Object> summary = new LinkedHashMap<>();
        int pass = 0, fail = 0, skip = 0;
        for (Result r : results) {
            switch (r.status) {
                case "PASS" -> pass++;
                case "FAIL" -> fail++;
                case "SKIP" -> skip++;
            }
        }
        summary.put("total", results.size());
        summary.put("pass", pass);
        summary.put("fail", fail);
        summary.put("skip", skip);
        root.put("summary", summary);

        List<Map<String, Object>> arr = new ArrayList<>();
        for (Result r : results) {
            Map<String, Object> m = new LinkedHashMap<>();
            m.put("framework", r.framework);
            m.put("category", r.category);
            m.put("name", r.name);
            m.put("status", r.status);
            m.put("duration_ms", r.durationMs);
            m.put("error_code", r.errorCode);
            m.put("error_message", r.errorMessage);
            arr.add(m);
        }
        root.put("results", arr);

        Gson gson = new GsonBuilder().setPrettyPrinting().disableHtmlEscaping().create();
        Files.writeString(file, gson.toJson(root), StandardCharsets.UTF_8);
    }

    private void writeMarkdown(Path file) throws IOException {
        StringBuilder sb = new StringBuilder();
        int pass = 0, fail = 0, skip = 0;
        for (Result r : results) {
            switch (r.status) {
                case "PASS" -> pass++;
                case "FAIL" -> fail++;
                case "SKIP" -> skip++;
            }
        }
        int total = results.size();
        sb.append("# MatrixOne Java ORM Compatibility — Summary\n\n");
        sb.append("- **Engine:** ").append(engine).append("\n");
        sb.append("- **Total scenarios run:** ").append(total).append("\n");
        sb.append("- **Pass:** ").append(pass)
                .append(" (").append(pct(pass, total)).append(")\n");
        sb.append("- **Fail:** ").append(fail)
                .append(" (").append(pct(fail, total)).append(")\n");
        sb.append("- **Skip:** ").append(skip)
                .append(" (").append(pct(skip, total)).append(")\n\n");

        // Pass/fail by framework
        sb.append("## Pass / fail by framework\n\n");
        sb.append("| Framework | Pass | Fail | Skip | Pass % |\n");
        sb.append("|---|--:|--:|--:|--:|\n");
        Map<String, int[]> byFw = new TreeMap<>();
        for (Result r : results) {
            int[] c = byFw.computeIfAbsent(r.framework, k -> new int[3]);
            switch (r.status) {
                case "PASS" -> c[0]++;
                case "FAIL" -> c[1]++;
                case "SKIP" -> c[2]++;
            }
        }
        for (var e : byFw.entrySet()) {
            int[] c = e.getValue();
            int t = c[0] + c[1] + c[2];
            sb.append("| ").append(e.getKey())
                    .append(" | ").append(c[0])
                    .append(" | ").append(c[1])
                    .append(" | ").append(c[2])
                    .append(" | ").append(pct(c[0], t)).append(" |\n");
        }
        sb.append("\n");

        // Pass/fail by category
        sb.append("## Pass / fail by category\n\n");
        sb.append("| Category | Pass | Fail | Skip |\n");
        sb.append("|---|--:|--:|--:|\n");
        Map<String, int[]> byCat = new TreeMap<>();
        for (Result r : results) {
            int[] c = byCat.computeIfAbsent(r.category, k -> new int[3]);
            switch (r.status) {
                case "PASS" -> c[0]++;
                case "FAIL" -> c[1]++;
                case "SKIP" -> c[2]++;
            }
        }
        for (var e : byCat.entrySet()) {
            int[] c = e.getValue();
            sb.append("| ").append(e.getKey())
                    .append(" | ").append(c[0])
                    .append(" | ").append(c[1])
                    .append(" | ").append(c[2]).append(" |\n");
        }
        sb.append("\n");

        // Failures by error signature
        sb.append("## Failures by error signature (error_code)\n\n");
        sb.append("| Error code | Count | Meaning |\n");
        sb.append("|---|--:|---|\n");
        Map<String, Integer> bySig = new LinkedHashMap<>();
        for (Result r : results) {
            if (!"FAIL".equals(r.status)) continue;
            String key = r.errorCode == null ? "(none)" : r.errorCode;
            bySig.merge(key, 1, Integer::sum);
        }
        bySig.entrySet().stream()
                .sorted(Map.Entry.<String, Integer>comparingByValue().reversed())
                .forEach(e -> sb.append("| ").append(e.getKey())
                        .append(" | ").append(e.getValue())
                        .append(" | ").append(meaning(e.getKey())).append(" |\n"));
        sb.append("\n");

        // Top distinct failure messages
        sb.append("## Top distinct failure messages\n\n");
        Map<String, Integer> byMsg = new LinkedHashMap<>();
        Map<String, String> sampleCode = new LinkedHashMap<>();
        for (Result r : results) {
            if (!"FAIL".equals(r.status)) continue;
            String key = normalizeMsg(r.errorMessage);
            byMsg.merge(key, 1, Integer::sum);
            sampleCode.putIfAbsent(key, r.errorCode);
        }
        sb.append("| Count | Code | Message (normalized) |\n");
        sb.append("|--:|---|---|\n");
        byMsg.entrySet().stream()
                .sorted(Map.Entry.<String, Integer>comparingByValue().reversed())
                .limit(40)
                .forEach(e -> sb.append("| ").append(e.getValue())
                        .append(" | ").append(sampleCode.get(e.getKey()))
                        .append(" | ").append(escapePipe(e.getKey())).append(" |\n"));
        sb.append("\n");

        // Behavior mismatches (data-integrity findings) listed explicitly
        sb.append("## BEHAVIOR mismatches (ran OK but differs from MySQL)\n\n");
        sb.append("| Framework | Name | Detail |\n");
        sb.append("|---|---|---|\n");
        List<Result> behaviors = new ArrayList<>();
        for (Result r : results) {
            if ("FAIL".equals(r.status) && "BEHAVIOR".equals(r.errorCode)) behaviors.add(r);
        }
        behaviors.sort(Comparator.comparing(r -> r.name));
        for (Result r : behaviors) {
            sb.append("| ").append(r.framework)
                    .append(" | ").append(escapePipe(r.name))
                    .append(" | ").append(escapePipe(r.errorMessage)).append(" |\n");
        }
        sb.append("\n");

        Files.writeString(file, sb.toString(), StandardCharsets.UTF_8);
    }

    private static String normalizeMsg(String msg) {
        if (msg == null) return "(no message)";
        // Strip variable identifiers (table names with random suffixes, numbers).
        String n = msg.replaceAll("\\b\\d{3,}\\b", "#")
                .replaceAll("_[0-9a-f]{6,}", "_X")
                .replaceAll("'[^']*'", "'_'");
        return ErrorClassifier.truncate(n);
    }

    private static String meaning(String code) {
        return switch (code) {
            case "20105" -> "function / operator not implemented";
            case "1064" -> "SQL parser / syntax not supported";
            case "BEHAVIOR" -> "ran OK but result differs from MySQL semantics";
            case "20101" -> "internal 'not implemented yet' (e.g. ROLLBACK TO SAVEPOINT)";
            case "20203" -> "stricter argument / type validation than MySQL";
            case "1690" -> "out-of-range / overflow";
            case "1062" -> "duplicate entry for key";
            case "SKIP" -> "skipped (precondition)";
            default -> "";
        };
    }

    private static String escapePipe(String s) {
        if (s == null) return "";
        return s.replace("|", "\\|").replace("\n", " ");
    }

    private static String pct(int n, int total) {
        if (total == 0) return "0.0%";
        return String.format("%.1f%%", 100.0 * n / total);
    }
}
