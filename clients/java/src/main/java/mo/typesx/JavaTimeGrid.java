package mo.typesx;

import mo.harness.Assert;
import mo.harness.Db;
import mo.harness.Runner;
import mo.harness.SkipException;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;
import java.time.Duration;
import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.time.OffsetDateTime;
import java.time.Period;
import java.time.ZoneOffset;
import java.time.ZonedDateTime;

/**
 * java.time <-> SQL temporal mapping grid. Exercises JDBC 4.2 setObject/getObject
 * with the java.time types across the SQL temporal column types, plus fractional
 * second precision and Duration/Period (no native SQL type).
 */
public final class JavaTimeGrid {

    // column type x java.time type bindings that are "natural".
    private record Binding(String key, String ddl, String jtType) {}

    private static final Binding[] BINDINGS = {
            new Binding("localdate_date", "DATE", "LocalDate"),
            new Binding("localtime_time", "TIME", "LocalTime"),
            new Binding("localdatetime_datetime", "DATETIME", "LocalDateTime"),
            new Binding("localdatetime_timestamp", "TIMESTAMP", "LocalDateTime"),
            new Binding("instant_timestamp", "TIMESTAMP", "Instant"),
            new Binding("offsetdatetime_timestamp", "TIMESTAMP", "OffsetDateTime"),
            new Binding("zoneddatetime_timestamp", "TIMESTAMP", "ZonedDateTime"),
            new Binding("offsetdatetime_datetime", "DATETIME", "OffsetDateTime"),
            new Binding("offsettime_time", "TIME", "java.time.OffsetTime"),
    };

    public static void register(Runner r, Db db, String fw, String dbName) {
        for (Binding b : BINDINGS) {
            final String cat = "javatime";

            r.register(fw, cat, "setObject_roundtrip/" + b.key, () -> {
                String t = Db.uniq("jt_" + b.key);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + b.ddl + ")");
                    }
                    Object val = sample(b.jtType);
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ps.setObject(1, val);
                        ps.executeUpdate();
                    }
                    try (PreparedStatement ps = c.prepareStatement("SELECT c FROM " + t + " WHERE id=1");
                         ResultSet rs = ps.executeQuery()) {
                        Assert.isTrue(rs.next(), "row present");
                        rs.getObject(1);
                        Assert.isTrue(!rs.wasNull(), "value not null for " + b.key);
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });

            r.register(fw, cat, "getObject_typed/" + b.key, () -> {
                String t = Db.uniq("jt_" + b.key);
                try (Connection c = db.connect(dbName)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + b.ddl + ")");
                    }
                    Object val = sample(b.jtType);
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ps.setObject(1, val);
                        ps.executeUpdate();
                    }
                    Class<?> getAs = getAsClass(b.jtType);
                    try (PreparedStatement ps = c.prepareStatement("SELECT c FROM " + t + " WHERE id=1");
                         ResultSet rs = ps.executeQuery()) {
                        rs.next();
                        Object got = rs.getObject(1, getAs);
                        Assert.notNull(got, "getObject(" + getAs.getSimpleName() + ") for " + b.key);
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // Fractional-second precision grid: DATETIME(0..6) / TIMESTAMP(0..6) / TIME(0..6)
        String[] fracBase = {"DATETIME", "TIMESTAMP", "TIME"};
        for (String base : fracBase) {
            for (int prec = 0; prec <= 6; prec++) {
                final String ddl = base + "(" + prec + ")";
                final String key = base.toLowerCase() + "_p" + prec;
                r.register(fw, "temporal_precision", "declare/" + key, () -> {
                    String t = Db.uniq("tp_" + key);
                    try (Connection c = db.connect(dbName); Statement st = c.createStatement()) {
                        try {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ddl + ")");
                        } finally { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
                r.register(fw, "temporal_precision", "roundtrip/" + key, () -> {
                    String t = Db.uniq("tp_" + key);
                    try (Connection c = db.connect(dbName)) {
                        try (Statement st = c.createStatement()) {
                            st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c " + ddl + ")");
                        }
                        Object val = base.equals("TIME")
                                ? LocalTime.of(12, 34, 56, 123456000)
                                : LocalDateTime.of(2024, 6, 15, 12, 34, 56, 123456000);
                        try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                            ps.setObject(1, val);
                            ps.executeUpdate();
                        }
                        try (Statement st = c.createStatement();
                             ResultSet rs = st.executeQuery("SELECT c FROM " + t + " WHERE id=1")) {
                            Assert.isTrue(rs.next(), "row present");
                            Assert.notNull(rs.getString(1), "fractional value for " + key);
                        }
                        try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                    }
                });
            }
        }

        // Timezone behavior: store via connection serverTimezone=UTC and a shifted zone.
        String[] zones = {"UTC", "America/New_York", "Asia/Tokyo", "Europe/Berlin"};
        for (String z : zones) {
            final String zkey = z.replaceAll("[^A-Za-z0-9]", "_");
            r.register(fw, "javatime", "timezone_param/" + zkey, () -> {
                String t = Db.uniq("tz_" + zkey);
                String url = db.config().dbUrl(dbName).replace("serverTimezone=UTC", "serverTimezone=" + z);
                try (Connection c = java.sql.DriverManager.getConnection(url, db.config().user, db.config().pass)) {
                    try (Statement st = c.createStatement()) {
                        st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, c TIMESTAMP)");
                    }
                    Instant inst = Instant.parse("2024-06-15T12:00:00Z");
                    try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                        ps.setObject(1, inst);
                        ps.executeUpdate();
                    }
                    try (PreparedStatement ps = c.prepareStatement("SELECT c FROM " + t + " WHERE id=1");
                         ResultSet rs = ps.executeQuery()) {
                        rs.next();
                        Assert.notNull(rs.getObject(1, Instant.class), "instant round-trip in zone " + z);
                    }
                    try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
                }
            });
        }

        // Duration / Period: no native SQL type -> store as numeric/string, exercise both.
        r.register(fw, "javatime", "duration_as_bigint_seconds", () -> {
            String t = Db.uniq("dur");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, secs BIGINT)");
                }
                Duration d = Duration.ofHours(3).plusMinutes(15);
                try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                    ps.setLong(1, d.toSeconds());
                    ps.executeUpdate();
                }
                long secs = Db.scalarLong(c, "SELECT secs FROM " + t + " WHERE id=1");
                Assert.eq(d.toSeconds(), secs, "duration seconds round-trip");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
        r.register(fw, "javatime", "period_as_iso_string", () -> {
            String t = Db.uniq("per");
            try (Connection c = db.connect(dbName)) {
                try (Statement st = c.createStatement()) {
                    st.execute("CREATE TABLE " + t + " (id INT PRIMARY KEY, iso VARCHAR(40))");
                }
                Period p = Period.of(1, 2, 3);
                try (PreparedStatement ps = c.prepareStatement("INSERT INTO " + t + " VALUES (1, ?)")) {
                    ps.setString(1, p.toString());
                    ps.executeUpdate();
                }
                String got = Db.scalarStr(c, "SELECT iso FROM " + t + " WHERE id=1");
                Assert.eq(p, Period.parse(got), "period ISO round-trip");
                try (Statement st = c.createStatement()) { Db.quiet(st, "DROP TABLE IF EXISTS " + t); }
            }
        });
    }

    private static Object sample(String jt) {
        return switch (jt) {
            case "LocalDate" -> LocalDate.of(2024, 6, 15);
            case "LocalTime" -> LocalTime.of(11, 22, 33);
            case "LocalDateTime" -> LocalDateTime.of(2024, 6, 15, 11, 22, 33);
            case "Instant" -> Instant.parse("2024-06-15T11:22:33Z");
            case "OffsetDateTime" -> OffsetDateTime.of(2024, 6, 15, 11, 22, 33, 0, ZoneOffset.ofHours(2));
            case "ZonedDateTime" -> ZonedDateTime.of(2024, 6, 15, 11, 22, 33, 0, ZoneOffset.ofHours(5));
            case "java.time.OffsetTime" -> java.time.OffsetTime.of(11, 22, 33, 0, ZoneOffset.ofHours(1));
            default -> throw new SkipException("unmapped java.time type " + jt);
        };
    }

    private static Class<?> getAsClass(String jt) {
        return switch (jt) {
            case "LocalDate" -> LocalDate.class;
            case "LocalTime" -> LocalTime.class;
            case "LocalDateTime" -> LocalDateTime.class;
            case "Instant" -> Instant.class;
            case "OffsetDateTime" -> OffsetDateTime.class;
            case "ZonedDateTime" -> OffsetDateTime.class; // driver maps to OffsetDateTime
            case "java.time.OffsetTime" -> java.time.OffsetTime.class;
            default -> Object.class;
        };
    }

    private JavaTimeGrid() {}
}
