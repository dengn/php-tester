'use strict';

// ---------------------------------------------------------------------------
// Objection.js (+ knex) against MatrixOne.
//
// Objection models ride on top of a single knex instance. We bind knex once,
// build the schema with knex.schema (createTable), and exercise the model
// layer: CRUD, relations (relatedQuery / withGraphFetched), modifiers,
// transactions, JSON attributes, eager loading.
// ---------------------------------------------------------------------------

const { Model } = require('objection');
const { objectionKnex, teardownObjection, withTimeout } = require('../lib/orm_shared');
const { BehaviorMismatch, expectTrue } = require('../lib/errors');

let bound = false;
function bind() {
  if (!bound) { Model.knex(objectionKnex()); bound = true; }
  return objectionKnex();
}

// ---- Models -------------------------------------------------------------
class Author extends Model {
  static get tableName() { return 'obj_author'; }
  static get relationMappings() {
    return {
      books: {
        relation: Model.HasManyRelation,
        modelClass: Book,
        join: { from: 'obj_author.id', to: 'obj_book.authorId' },
      },
    };
  }
}
class Book extends Model {
  static get tableName() { return 'obj_book'; }
  static get jsonAttributes() { return ['tags']; }
  static get relationMappings() {
    return {
      author: {
        relation: Model.BelongsToOneRelation,
        modelClass: Author,
        join: { from: 'obj_book.authorId', to: 'obj_author.id' },
      },
    };
  }
}

let schemaReady = false;
async function ensureSchema() {
  if (schemaReady) return;
  const k = bind();
  await k.schema.dropTableIfExists('obj_book');
  await k.schema.dropTableIfExists('obj_author');
  await k.schema.createTable('obj_author', (t) => {
    t.increments('id').primary();
    t.string('name');
    t.integer('age');
  });
  await k.schema.createTable('obj_book', (t) => {
    t.increments('id').primary();
    t.string('title');
    t.decimal('price', 12, 2);
    t.json('tags');
    t.integer('authorId');
  });
  schemaReady = true;
}
async function reset() {
  const k = bind();
  await k('obj_book').del();
  await k('obj_author').del();
}

const OP_TIMEOUT = 12000;

function register(runner) {
  const FW = 'objection';

  const t = (cat, name, fn) => runner.add(FW, cat, name, async () => {
    await ensureSchema();
    await reset();
    return withTimeout(fn(), OP_TIMEOUT, name);
  });

  // ---- CRUD ----
  t('crud', 'insert-returns-row', async () => {
    const a = await Author.query().insert({ name: 'Tolkien', age: 81 });
    if (!a.id) throw new BehaviorMismatch('insert did not return id');
  });
  t('crud', 'insertAndFetch', async () => {
    const a = await Author.query().insertAndFetch({ name: 'A' });
    if (!a.id) throw new BehaviorMismatch('insertAndFetch missing id');
  });
  t('crud', 'findById', async () => {
    const a = await Author.query().insert({ name: 'B' });
    const f = await Author.query().findById(a.id);
    if (!f || f.name !== 'B') throw new BehaviorMismatch('findById mismatch');
  });
  t('crud', 'patch', async () => {
    const a = await Author.query().insert({ name: 'C', age: 1 });
    await Author.query().findById(a.id).patch({ age: 2 });
    const f = await Author.query().findById(a.id);
    if (f.age !== 2) throw new BehaviorMismatch('patch failed');
  });
  t('crud', 'patchAndFetchById', async () => {
    const a = await Author.query().insert({ name: 'D', age: 1 });
    const f = await Author.query().patchAndFetchById(a.id, { age: 9 });
    if (f.age !== 9) throw new BehaviorMismatch('patchAndFetchById failed');
  });
  t('crud', 'delete', async () => {
    const a = await Author.query().insert({ name: 'E' });
    await Author.query().deleteById(a.id);
    const n = await Author.query().resultSize();
    if (n !== 0) throw new BehaviorMismatch(`after delete size=${n}`);
  });
  t('crud', 'insertGraph', async () => {
    const a = await Author.query().insertGraph({
      name: 'Graph', books: [{ title: 'b1' }, { title: 'b2' }],
    }, { relate: false });
    if (!a.id) throw new BehaviorMismatch('insertGraph failed');
  });

  // ---- query building ----
  t('query', 'where-eq', async () => {
    await Author.query().insertGraph([{ name: 'x', age: 10 }, { name: 'y', age: 20 }]);
    const r = await Author.query().where('age', '>', 15);
    if (r.length !== 1) throw new BehaviorMismatch(`where count=${r.length}`);
  });
  t('query', 'whereIn', async () => {
    await Author.query().insert([{ name: 'a' }, { name: 'b' }, { name: 'c' }]);
    const r = await Author.query().whereIn('name', ['a', 'c']);
    if (r.length !== 2) throw new BehaviorMismatch(`whereIn count=${r.length}`);
  });
  t('query', 'orderBy-limit', async () => {
    await Author.query().insert([{ name: 'a', age: 3 }, { name: 'b', age: 1 }, { name: 'c', age: 2 }]);
    const r = await Author.query().orderBy('age').limit(2);
    if (r[0].age !== 1) throw new BehaviorMismatch('orderBy/limit failed');
  });
  t('query', 'count', async () => {
    await Author.query().insert([{ name: 'a' }, { name: 'b' }]);
    const n = await Author.query().resultSize();
    if (n !== 2) throw new BehaviorMismatch(`count=${n}`);
  });
  t('query', 'page', async () => {
    for (let i = 0; i < 10; i++) await Author.query().insert({ name: `p${i}`, age: i });
    const res = await Author.query().orderBy('age').page(1, 3);
    if (res.results.length !== 3 || Number(res.total) !== 10) throw new BehaviorMismatch('page failed');
  });
  t('query', 'aggregate-avg', async () => {
    await Author.query().insert([{ name: 'a', age: 10 }, { name: 'b', age: 20 }]);
    const r = await Author.query().avg('age as a').first();
    if (Number(r.a) !== 15) throw new BehaviorMismatch(`avg=${r.a}`);
  });

  // ---- relations ----
  t('relation', 'withGraphFetched', async () => {
    const a = await Author.query().insert({ name: 'rel' });
    await Book.query().insert([{ title: 'b1', authorId: a.id }, { title: 'b2', authorId: a.id }]);
    const f = await Author.query().findById(a.id).withGraphFetched('books');
    if (f.books.length !== 2) throw new BehaviorMismatch(`withGraphFetched books=${f.books.length}`);
  });
  t('relation', 'relatedQuery', async () => {
    const a = await Author.query().insert({ name: 'rq' });
    await a.$relatedQuery('books').insert({ title: 'rb' });
    const books = await Author.relatedQuery('books').for(a.id);
    if (books.length !== 1) throw new BehaviorMismatch('relatedQuery failed');
  });
  t('relation', 'belongsToOne-eager', async () => {
    const a = await Author.query().insert({ name: 'owner' });
    const b = await Book.query().insert({ title: 'owned', authorId: a.id });
    const f = await Book.query().findById(b.id).withGraphFetched('author');
    if (!f.author || f.author.name !== 'owner') throw new BehaviorMismatch('belongsToOne eager failed');
  });
  t('relation', 'joinRelated', async () => {
    const a = await Author.query().insert({ name: 'jr' });
    await Book.query().insert({ title: 'jrb', authorId: a.id });
    const r = await Author.query().joinRelated('books').where('books.title', 'jrb');
    if (r.length !== 1) throw new BehaviorMismatch('joinRelated failed');
  });

  // ---- JSON attributes ----
  t('json', 'json-attribute-roundtrip', async () => {
    const a = await Author.query().insert({ name: 'j' });
    const b = await Book.query().insert({ title: 'jb', authorId: a.id, tags: ['x', 'y', 'z'] });
    const f = await Book.query().findById(b.id);
    if (!Array.isArray(f.tags) || f.tags.length !== 3) throw new BehaviorMismatch('json attr roundtrip failed');
  });

  // ---- transactions ----
  t('transaction', 'commit', async () => {
    const k = bind();
    await Author.transaction(k, async (trx) => {
      await Author.query(trx).insert({ name: 'tx1' });
      await Author.query(trx).insert({ name: 'tx2' });
    });
    const n = await Author.query().resultSize();
    if (n !== 2) throw new BehaviorMismatch(`tx commit count=${n}`);
  });
  t('transaction', 'rollback', async () => {
    const k = bind();
    try {
      await Author.transaction(k, async (trx) => {
        await Author.query(trx).insert({ name: 'rb1' });
        throw new Error('boom');
      });
    } catch (_) { /* expected */ }
    const n = await Author.query().resultSize();
    if (n !== 0) throw new BehaviorMismatch(`rollback left ${n}`);
  });

  // ---- decimal handling ----
  t('types', 'decimal-roundtrip', async () => {
    const a = await Author.query().insert({ name: 'dec' });
    const b = await Book.query().insert({ title: 'db', authorId: a.id, price: '123.45' });
    const f = await Book.query().findById(b.id);
    if (String(f.price) !== '123.45') throw new BehaviorMismatch(`decimal=${f.price}`);
  });
}

async function teardown() { await teardownObjection(); }

module.exports = { register, teardown };
