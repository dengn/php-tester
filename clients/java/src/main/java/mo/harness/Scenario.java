package mo.harness;

/** A single registered test scenario. */
public final class Scenario {
    public final String framework;
    public final String category;
    public final String name;
    public final ScenarioBody body;

    public Scenario(String framework, String category, String name, ScenarioBody body) {
        this.framework = framework;
        this.category = category;
        this.name = name;
        this.body = body;
    }
}
