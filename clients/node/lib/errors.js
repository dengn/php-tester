'use strict';

// A scenario throws BehaviorMismatch when it ran without a driver error but the
// result differs from MySQL semantics. The runner records these as error_code
// "BEHAVIOR".
class BehaviorMismatch extends Error {
  constructor(message) {
    super(message);
    this.name = 'BehaviorMismatch';
    this.isBehaviorMismatch = true;
  }
}

// A scenario throws SkipScenario to be recorded as SKIP (precondition not met,
// not applicable, etc.).
class SkipScenario extends Error {
  constructor(message) {
    super(message);
    this.name = 'SkipScenario';
    this.isSkip = true;
  }
}

// Assertion helper that raises a BehaviorMismatch (so it is classified as a
// MatrixOne-vs-MySQL behaviour difference, never as a harness crash).
function expect(actual, expected, label) {
  const a = JSON.stringify(actual);
  const e = JSON.stringify(expected);
  if (a !== e) {
    throw new BehaviorMismatch(`${label || 'value'}: expected ${e} but got ${a}`);
  }
}

function expectTrue(cond, label) {
  if (!cond) {
    throw new BehaviorMismatch(label || 'expected condition to be true');
  }
}

/**
 * Pull a vendor-agnostic error code out of whatever exception bubbled up.
 * MatrixOne surfaces MySQL-style numeric codes via the mysql2 driver's `errno`
 * (e.g. 1064, 20105, 20101, 1690, 1062) and string `code` (e.g. ER_PARSE_ERROR).
 * Some errors only carry the numeric code inside the message text, so we mine
 * that as a fallback. ORMs (Sequelize/TypeORM) wrap the driver error; we unwrap.
 *
 * Returns { code: string, message: string }.
 */
function classify(err) {
  if (err && err.isBehaviorMismatch) {
    return { code: 'BEHAVIOR', message: err.message };
  }

  // Unwrap ORM wrapper errors to reach the underlying driver error.
  let e = err;
  const seen = new Set();
  while (e && !seen.has(e)) {
    seen.add(e);
    // Sequelize: err.original / err.parent; TypeORM: err.driverError
    const inner = e.original || e.parent || e.driverError || e.cause;
    if (inner && inner !== e && (inner.errno || inner.sqlState || inner.code)) {
      e = inner;
      continue;
    }
    break;
  }

  let message = (e && e.message) || (err && err.message) || String(err);

  // Primary: numeric MatrixOne / MySQL error number from the driver.
  let code = null;
  if (e && typeof e.errno === 'number' && e.errno !== 0) {
    code = String(e.errno);
  }

  // Mine the message for a MatrixOne numeric code if the driver did not expose
  // one (some MO internal errors arrive as plain strings).
  if (!code) {
    let m = message.match(/\b(20\d{3})\b/); // MatrixOne internal codes 20xxx
    if (m) {
      code = m[1];
    } else {
      m = message.match(/(?:errno|error)\s*[:=]?\s*(\d{3,5})/i);
      if (m) code = m[1];
    }
  }

  // Driver string code as a labelled fallback (ER_PARSE_ERROR, ECONNREFUSED…).
  if (!code) {
    if (e && e.code) {
      code = String(e.code);
    } else if (/not (?:been )?implemented/i.test(message)) {
      code = 'UNIMPLEMENTED';
    } else if (/not supported/i.test(message)) {
      code = 'UNSUPPORTED';
    } else {
      code = 'ERROR';
    }
  }

  return { code, message };
}

module.exports = { BehaviorMismatch, SkipScenario, expect, expectTrue, classify };
