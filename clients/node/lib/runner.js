'use strict';

const { classify } = require('./errors');

const PASS = 'PASS';
const FAIL = 'FAIL';
const SKIP = 'SKIP';

/**
 * Async scenario runner.
 *
 * A scenario is registered with (framework, category, name, async fn). The fn
 * performs some operation against MatrixOne and:
 *   - resolves (any value)              => PASS
 *   - throws SkipScenario               => SKIP
 *   - throws BehaviorMismatch           => FAIL with error_code "BEHAVIOR"
 *   - throws anything else              => FAIL (driver / incompatibility error)
 *
 * Scenarios run sequentially (await each) to keep load manageable on the single
 * MatrixOne node. Each is fully isolated by try/catch so one explosion never
 * aborts the run; timing and error classification are captured per scenario.
 */
class Runner {
  constructor(opts = {}) {
    this.scenarios = [];
    this.results = [];
    this.verbose = !!opts.verbose;
    // Per-scenario wall-clock cap. A scenario that exceeds it is recorded as a
    // FAIL ("TIMEOUT") rather than stalling the whole suite. This is a safety
    // net for operations where MatrixOne neither returns nor errors (e.g. some
    // ORM schema-introspection paths against missing information_schema tables).
    this._timeoutMs = opts.timeoutMs || 30000;
    this._nextId = 1;
  }

  // register(framework, category, name, fn)
  add(framework, category, name, fn) {
    this.scenarios.push({ framework, category, name, fn });
  }

  count() {
    return this.scenarios.length;
  }

  filter(predicate) {
    this.scenarios = this.scenarios.filter(predicate);
  }

  async run(progressEvery = 100) {
    const total = this.scenarios.length;
    let done = 0;
    for (const s of this.scenarios) {
      const id = this._nextId++;
      const start = Date.now();
      let status = PASS;
      let errorCode = null;
      let errorMessage = null;
      let detail = null;

      let timer = null;
      try {
        const timeout = new Promise((_, reject) => {
          timer = setTimeout(() => reject(new ScenarioTimeout(this._timeoutMs)), this._timeoutMs);
        });
        const r = await Promise.race([Promise.resolve(s.fn()), timeout]);
        if (typeof r === 'string') detail = r;
      } catch (err) {
        if (err && err.isSkip) {
          status = SKIP;
          detail = err.message;
        } else if (err && err.isTimeout) {
          status = FAIL;
          errorCode = 'TIMEOUT';
          errorMessage = err.message;
        } else {
          status = FAIL;
          const c = classify(err);
          errorCode = c.code;
          errorMessage = c.message;
        }
      } finally {
        if (timer) clearTimeout(timer);
      }

      const durationMs = Date.now() - start;
      this.results.push({
        id,
        framework: s.framework,
        category: s.category,
        name: s.name,
        status,
        durationMs,
        detail: typeof detail === 'string' ? detail : null,
        error_code: errorCode,
        error_message: errorMessage,
      });

      done++;
      if (this.verbose && status === FAIL) {
        process.stderr.write(
          `\n[FAIL #${id}] ${s.framework}/${s.category}/${s.name}\n        (${errorCode}) ${short(errorMessage)}\n`
        );
      }
      if (done % progressEvery === 0 || done === total) {
        process.stdout.write(`  ...${done}/${total}\n`);
      }
    }
  }

  summary() {
    let pass = 0, fail = 0, skip = 0;
    for (const r of this.results) {
      if (r.status === PASS) pass++;
      else if (r.status === FAIL) fail++;
      else skip++;
    }
    return { total: this.results.length, pass, fail, skip };
  }
}

class ScenarioTimeout extends Error {
  constructor(ms) {
    super(`scenario exceeded ${ms}ms wall-clock cap (likely a non-returning MatrixOne operation)`);
    this.isTimeout = true;
  }
}

function short(s, len = 200) {
  if (!s) return '';
  s = String(s).replace(/\s+/g, ' ').trim();
  return s.length > len ? s.slice(0, len) + '…' : s;
}

module.exports = { Runner, PASS, FAIL, SKIP, short };
