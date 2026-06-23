
/**
 * Client
**/

import * as runtime from './runtime/client.js';
import $Types = runtime.Types // general types
import $Public = runtime.Types.Public
import $Utils = runtime.Types.Utils
import $Extensions = runtime.Types.Extensions
import $Result = runtime.Types.Result

export type PrismaPromise<T> = $Public.PrismaPromise<T>


/**
 * Model PUser
 * 
 */
export type PUser = $Result.DefaultSelection<Prisma.$PUserPayload>
/**
 * Model PPost
 * 
 */
export type PPost = $Result.DefaultSelection<Prisma.$PPostPayload>
/**
 * Model PProduct
 * 
 */
export type PProduct = $Result.DefaultSelection<Prisma.$PProductPayload>

/**
 * ##  Prisma Client ʲˢ
 *
 * Type-safe database client for TypeScript & Node.js
 * @example
 * ```
 * const prisma = new PrismaClient({
 *   adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL })
 * })
 * // Fetch zero or more PUsers
 * const pUsers = await prisma.pUser.findMany()
 * ```
 *
 *
 * Read more in our [docs](https://pris.ly/d/client).
 */
export class PrismaClient<
  ClientOptions extends Prisma.PrismaClientOptions = Prisma.PrismaClientOptions,
  const U = 'log' extends keyof ClientOptions ? ClientOptions['log'] extends Array<Prisma.LogLevel | Prisma.LogDefinition> ? Prisma.GetEvents<ClientOptions['log']> : never : never,
  ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs
> {
  [K: symbol]: { types: Prisma.TypeMap<ExtArgs>['other'] }

    /**
   * ##  Prisma Client ʲˢ
   *
   * Type-safe database client for TypeScript & Node.js
   * @example
   * ```
   * const prisma = new PrismaClient({
   *   adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL })
   * })
   * // Fetch zero or more PUsers
   * const pUsers = await prisma.pUser.findMany()
   * ```
   *
   *
   * Read more in our [docs](https://pris.ly/d/client).
   */

  constructor(optionsArg ?: Prisma.Subset<ClientOptions, Prisma.PrismaClientOptions>);
  $on<V extends U>(eventType: V, callback: (event: V extends 'query' ? Prisma.QueryEvent : Prisma.LogEvent) => void): PrismaClient;

  /**
   * Connect with the database
   */
  $connect(): $Utils.JsPromise<void>;

  /**
   * Disconnect from the database
   */
  $disconnect(): $Utils.JsPromise<void>;

/**
   * Executes a prepared raw query and returns the number of affected rows.
   * @example
   * ```
   * const result = await prisma.$executeRaw`UPDATE User SET cool = ${true} WHERE email = ${'user@email.com'};`
   * ```
   *
   * Read more in our [docs](https://pris.ly/d/raw-queries).
   */
  $executeRaw<T = unknown>(query: TemplateStringsArray | Prisma.Sql, ...values: any[]): Prisma.PrismaPromise<number>;

  /**
   * Executes a raw query and returns the number of affected rows.
   * Susceptible to SQL injections, see documentation.
   * @example
   * ```
   * const result = await prisma.$executeRawUnsafe('UPDATE User SET cool = $1 WHERE email = $2 ;', true, 'user@email.com')
   * ```
   *
   * Read more in our [docs](https://pris.ly/d/raw-queries).
   */
  $executeRawUnsafe<T = unknown>(query: string, ...values: any[]): Prisma.PrismaPromise<number>;

  /**
   * Performs a prepared raw query and returns the `SELECT` data.
   * @example
   * ```
   * const result = await prisma.$queryRaw`SELECT * FROM User WHERE id = ${1} OR email = ${'user@email.com'};`
   * ```
   *
   * Read more in our [docs](https://pris.ly/d/raw-queries).
   */
  $queryRaw<T = unknown>(query: TemplateStringsArray | Prisma.Sql, ...values: any[]): Prisma.PrismaPromise<T>;

  /**
   * Performs a raw query and returns the `SELECT` data.
   * Susceptible to SQL injections, see documentation.
   * @example
   * ```
   * const result = await prisma.$queryRawUnsafe('SELECT * FROM User WHERE id = $1 OR email = $2;', 1, 'user@email.com')
   * ```
   *
   * Read more in our [docs](https://pris.ly/d/raw-queries).
   */
  $queryRawUnsafe<T = unknown>(query: string, ...values: any[]): Prisma.PrismaPromise<T>;


  /**
   * Allows the running of a sequence of read/write operations that are guaranteed to either succeed or fail as a whole.
   * @example
   * ```
   * const [george, bob, alice] = await prisma.$transaction([
   *   prisma.user.create({ data: { name: 'George' } }),
   *   prisma.user.create({ data: { name: 'Bob' } }),
   *   prisma.user.create({ data: { name: 'Alice' } }),
   * ])
   * ```
   * 
   * Read more in our [docs](https://www.prisma.io/docs/orm/prisma-client/queries/transactions).
   */
  $transaction<P extends Prisma.PrismaPromise<any>[]>(arg: [...P], options?: { maxWait?: number, timeout?: number, isolationLevel?: Prisma.TransactionIsolationLevel }): $Utils.JsPromise<runtime.Types.Utils.UnwrapTuple<P>>

  $transaction<R>(fn: (prisma: Omit<PrismaClient, runtime.ITXClientDenyList>) => $Utils.JsPromise<R>, options?: { maxWait?: number, timeout?: number, isolationLevel?: Prisma.TransactionIsolationLevel }): $Utils.JsPromise<R>

  $extends: $Extensions.ExtendsHook<"extends", Prisma.TypeMapCb<ClientOptions>, ExtArgs, $Utils.Call<Prisma.TypeMapCb<ClientOptions>, {
    extArgs: ExtArgs
  }>>

      /**
   * `prisma.pUser`: Exposes CRUD operations for the **PUser** model.
    * Example usage:
    * ```ts
    * // Fetch zero or more PUsers
    * const pUsers = await prisma.pUser.findMany()
    * ```
    */
  get pUser(): Prisma.PUserDelegate<ExtArgs, ClientOptions>;

  /**
   * `prisma.pPost`: Exposes CRUD operations for the **PPost** model.
    * Example usage:
    * ```ts
    * // Fetch zero or more PPosts
    * const pPosts = await prisma.pPost.findMany()
    * ```
    */
  get pPost(): Prisma.PPostDelegate<ExtArgs, ClientOptions>;

  /**
   * `prisma.pProduct`: Exposes CRUD operations for the **PProduct** model.
    * Example usage:
    * ```ts
    * // Fetch zero or more PProducts
    * const pProducts = await prisma.pProduct.findMany()
    * ```
    */
  get pProduct(): Prisma.PProductDelegate<ExtArgs, ClientOptions>;
}

export namespace Prisma {
  export import DMMF = runtime.DMMF

  export type PrismaPromise<T> = $Public.PrismaPromise<T>

  /**
   * Validator
   */
  export import validator = runtime.Public.validator

  /**
   * Prisma Errors
   */
  export import PrismaClientKnownRequestError = runtime.PrismaClientKnownRequestError
  export import PrismaClientUnknownRequestError = runtime.PrismaClientUnknownRequestError
  export import PrismaClientRustPanicError = runtime.PrismaClientRustPanicError
  export import PrismaClientInitializationError = runtime.PrismaClientInitializationError
  export import PrismaClientValidationError = runtime.PrismaClientValidationError

  /**
   * Re-export of sql-template-tag
   */
  export import sql = runtime.sqltag
  export import empty = runtime.empty
  export import join = runtime.join
  export import raw = runtime.raw
  export import Sql = runtime.Sql



  /**
   * Decimal.js
   */
  export import Decimal = runtime.Decimal

  export type DecimalJsLike = runtime.DecimalJsLike

  /**
  * Extensions
  */
  export import Extension = $Extensions.UserArgs
  export import getExtensionContext = runtime.Extensions.getExtensionContext
  export import Args = $Public.Args
  export import Payload = $Public.Payload
  export import Result = $Public.Result
  export import Exact = $Public.Exact

  /**
   * Prisma Client JS version: 7.8.0
   * Query Engine version: 3c6e192761c0362d496ed980de936e2f3cebcd3a
   */
  export type PrismaVersion = {
    client: string
    engine: string
  }

  export const prismaVersion: PrismaVersion

  /**
   * Utility Types
   */


  export import Bytes = runtime.Bytes
  export import JsonObject = runtime.JsonObject
  export import JsonArray = runtime.JsonArray
  export import JsonValue = runtime.JsonValue
  export import InputJsonObject = runtime.InputJsonObject
  export import InputJsonArray = runtime.InputJsonArray
  export import InputJsonValue = runtime.InputJsonValue

  /**
   * Types of the values used to represent different kinds of `null` values when working with JSON fields.
   *
   * @see https://www.prisma.io/docs/concepts/components/prisma-client/working-with-fields/working-with-json-fields#filtering-on-a-json-field
   */
  namespace NullTypes {
    /**
    * Type of `Prisma.DbNull`.
    *
    * You cannot use other instances of this class. Please use the `Prisma.DbNull` value.
    *
    * @see https://www.prisma.io/docs/concepts/components/prisma-client/working-with-fields/working-with-json-fields#filtering-on-a-json-field
    */
    class DbNull {
      private DbNull: never
      private constructor()
    }

    /**
    * Type of `Prisma.JsonNull`.
    *
    * You cannot use other instances of this class. Please use the `Prisma.JsonNull` value.
    *
    * @see https://www.prisma.io/docs/concepts/components/prisma-client/working-with-fields/working-with-json-fields#filtering-on-a-json-field
    */
    class JsonNull {
      private JsonNull: never
      private constructor()
    }

    /**
    * Type of `Prisma.AnyNull`.
    *
    * You cannot use other instances of this class. Please use the `Prisma.AnyNull` value.
    *
    * @see https://www.prisma.io/docs/concepts/components/prisma-client/working-with-fields/working-with-json-fields#filtering-on-a-json-field
    */
    class AnyNull {
      private AnyNull: never
      private constructor()
    }
  }

  /**
   * Helper for filtering JSON entries that have `null` on the database (empty on the db)
   *
   * @see https://www.prisma.io/docs/concepts/components/prisma-client/working-with-fields/working-with-json-fields#filtering-on-a-json-field
   */
  export const DbNull: NullTypes.DbNull

  /**
   * Helper for filtering JSON entries that have JSON `null` values (not empty on the db)
   *
   * @see https://www.prisma.io/docs/concepts/components/prisma-client/working-with-fields/working-with-json-fields#filtering-on-a-json-field
   */
  export const JsonNull: NullTypes.JsonNull

  /**
   * Helper for filtering JSON entries that are `Prisma.DbNull` or `Prisma.JsonNull`
   *
   * @see https://www.prisma.io/docs/concepts/components/prisma-client/working-with-fields/working-with-json-fields#filtering-on-a-json-field
   */
  export const AnyNull: NullTypes.AnyNull

  type SelectAndInclude = {
    select: any
    include: any
  }

  type SelectAndOmit = {
    select: any
    omit: any
  }

  /**
   * Get the type of the value, that the Promise holds.
   */
  export type PromiseType<T extends PromiseLike<any>> = T extends PromiseLike<infer U> ? U : T;

  /**
   * Get the return type of a function which returns a Promise.
   */
  export type PromiseReturnType<T extends (...args: any) => $Utils.JsPromise<any>> = PromiseType<ReturnType<T>>

  /**
   * From T, pick a set of properties whose keys are in the union K
   */
  type Prisma__Pick<T, K extends keyof T> = {
      [P in K]: T[P];
  };


  export type Enumerable<T> = T | Array<T>;

  export type RequiredKeys<T> = {
    [K in keyof T]-?: {} extends Prisma__Pick<T, K> ? never : K
  }[keyof T]

  export type TruthyKeys<T> = keyof {
    [K in keyof T as T[K] extends false | undefined | null ? never : K]: K
  }

  export type TrueKeys<T> = TruthyKeys<Prisma__Pick<T, RequiredKeys<T>>>

  /**
   * Subset
   * @desc From `T` pick properties that exist in `U`. Simple version of Intersection
   */
  export type Subset<T, U> = {
    [key in keyof T]: key extends keyof U ? T[key] : never;
  };

  /**
   * SelectSubset
   * @desc From `T` pick properties that exist in `U`. Simple version of Intersection.
   * Additionally, it validates, if both select and include are present. If the case, it errors.
   */
  export type SelectSubset<T, U> = {
    [key in keyof T]: key extends keyof U ? T[key] : never
  } &
    (T extends SelectAndInclude
      ? 'Please either choose `select` or `include`.'
      : T extends SelectAndOmit
        ? 'Please either choose `select` or `omit`.'
        : {})

  /**
   * Subset + Intersection
   * @desc From `T` pick properties that exist in `U` and intersect `K`
   */
  export type SubsetIntersection<T, U, K> = {
    [key in keyof T]: key extends keyof U ? T[key] : never
  } &
    K

  type Without<T, U> = { [P in Exclude<keyof T, keyof U>]?: never };

  /**
   * XOR is needed to have a real mutually exclusive union type
   * https://stackoverflow.com/questions/42123407/does-typescript-support-mutually-exclusive-types
   */
  type XOR<T, U> =
    T extends object ?
    U extends object ?
      (Without<T, U> & U) | (Without<U, T> & T)
    : U : T


  /**
   * Is T a Record?
   */
  type IsObject<T extends any> = T extends Array<any>
  ? False
  : T extends Date
  ? False
  : T extends Uint8Array
  ? False
  : T extends BigInt
  ? False
  : T extends object
  ? True
  : False


  /**
   * If it's T[], return T
   */
  export type UnEnumerate<T extends unknown> = T extends Array<infer U> ? U : T

  /**
   * From ts-toolbelt
   */

  type __Either<O extends object, K extends Key> = Omit<O, K> &
    {
      // Merge all but K
      [P in K]: Prisma__Pick<O, P & keyof O> // With K possibilities
    }[K]

  type EitherStrict<O extends object, K extends Key> = Strict<__Either<O, K>>

  type EitherLoose<O extends object, K extends Key> = ComputeRaw<__Either<O, K>>

  type _Either<
    O extends object,
    K extends Key,
    strict extends Boolean
  > = {
    1: EitherStrict<O, K>
    0: EitherLoose<O, K>
  }[strict]

  type Either<
    O extends object,
    K extends Key,
    strict extends Boolean = 1
  > = O extends unknown ? _Either<O, K, strict> : never

  export type Union = any

  type PatchUndefined<O extends object, O1 extends object> = {
    [K in keyof O]: O[K] extends undefined ? At<O1, K> : O[K]
  } & {}

  /** Helper Types for "Merge" **/
  export type IntersectOf<U extends Union> = (
    U extends unknown ? (k: U) => void : never
  ) extends (k: infer I) => void
    ? I
    : never

  export type Overwrite<O extends object, O1 extends object> = {
      [K in keyof O]: K extends keyof O1 ? O1[K] : O[K];
  } & {};

  type _Merge<U extends object> = IntersectOf<Overwrite<U, {
      [K in keyof U]-?: At<U, K>;
  }>>;

  type Key = string | number | symbol;
  type AtBasic<O extends object, K extends Key> = K extends keyof O ? O[K] : never;
  type AtStrict<O extends object, K extends Key> = O[K & keyof O];
  type AtLoose<O extends object, K extends Key> = O extends unknown ? AtStrict<O, K> : never;
  export type At<O extends object, K extends Key, strict extends Boolean = 1> = {
      1: AtStrict<O, K>;
      0: AtLoose<O, K>;
  }[strict];

  export type ComputeRaw<A extends any> = A extends Function ? A : {
    [K in keyof A]: A[K];
  } & {};

  export type OptionalFlat<O> = {
    [K in keyof O]?: O[K];
  } & {};

  type _Record<K extends keyof any, T> = {
    [P in K]: T;
  };

  // cause typescript not to expand types and preserve names
  type NoExpand<T> = T extends unknown ? T : never;

  // this type assumes the passed object is entirely optional
  type AtLeast<O extends object, K extends string> = NoExpand<
    O extends unknown
    ? | (K extends keyof O ? { [P in K]: O[P] } & O : O)
      | {[P in keyof O as P extends K ? P : never]-?: O[P]} & O
    : never>;

  type _Strict<U, _U = U> = U extends unknown ? U & OptionalFlat<_Record<Exclude<Keys<_U>, keyof U>, never>> : never;

  export type Strict<U extends object> = ComputeRaw<_Strict<U>>;
  /** End Helper Types for "Merge" **/

  export type Merge<U extends object> = ComputeRaw<_Merge<Strict<U>>>;

  /**
  A [[Boolean]]
  */
  export type Boolean = True | False

  // /**
  // 1
  // */
  export type True = 1

  /**
  0
  */
  export type False = 0

  export type Not<B extends Boolean> = {
    0: 1
    1: 0
  }[B]

  export type Extends<A1 extends any, A2 extends any> = [A1] extends [never]
    ? 0 // anything `never` is false
    : A1 extends A2
    ? 1
    : 0

  export type Has<U extends Union, U1 extends Union> = Not<
    Extends<Exclude<U1, U>, U1>
  >

  export type Or<B1 extends Boolean, B2 extends Boolean> = {
    0: {
      0: 0
      1: 1
    }
    1: {
      0: 1
      1: 1
    }
  }[B1][B2]

  export type Keys<U extends Union> = U extends unknown ? keyof U : never

  type Cast<A, B> = A extends B ? A : B;

  export const type: unique symbol;



  /**
   * Used by group by
   */

  export type GetScalarType<T, O> = O extends object ? {
    [P in keyof T]: P extends keyof O
      ? O[P]
      : never
  } : never

  type FieldPaths<
    T,
    U = Omit<T, '_avg' | '_sum' | '_count' | '_min' | '_max'>
  > = IsObject<T> extends True ? U : T

  type GetHavingFields<T> = {
    [K in keyof T]: Or<
      Or<Extends<'OR', K>, Extends<'AND', K>>,
      Extends<'NOT', K>
    > extends True
      ? // infer is only needed to not hit TS limit
        // based on the brilliant idea of Pierre-Antoine Mills
        // https://github.com/microsoft/TypeScript/issues/30188#issuecomment-478938437
        T[K] extends infer TK
        ? GetHavingFields<UnEnumerate<TK> extends object ? Merge<UnEnumerate<TK>> : never>
        : never
      : {} extends FieldPaths<T[K]>
      ? never
      : K
  }[keyof T]

  /**
   * Convert tuple to union
   */
  type _TupleToUnion<T> = T extends (infer E)[] ? E : never
  type TupleToUnion<K extends readonly any[]> = _TupleToUnion<K>
  type MaybeTupleToUnion<T> = T extends any[] ? TupleToUnion<T> : T

  /**
   * Like `Pick`, but additionally can also accept an array of keys
   */
  type PickEnumerable<T, K extends Enumerable<keyof T> | keyof T> = Prisma__Pick<T, MaybeTupleToUnion<K>>

  /**
   * Exclude all keys with underscores
   */
  type ExcludeUnderscoreKeys<T extends string> = T extends `_${string}` ? never : T


  export type FieldRef<Model, FieldType> = runtime.FieldRef<Model, FieldType>

  type FieldRefInputType<Model, FieldType> = Model extends never ? never : FieldRef<Model, FieldType>


  export const ModelName: {
    PUser: 'PUser',
    PPost: 'PPost',
    PProduct: 'PProduct'
  };

  export type ModelName = (typeof ModelName)[keyof typeof ModelName]



  interface TypeMapCb<ClientOptions = {}> extends $Utils.Fn<{extArgs: $Extensions.InternalArgs }, $Utils.Record<string, any>> {
    returns: Prisma.TypeMap<this['params']['extArgs'], ClientOptions extends { omit: infer OmitOptions } ? OmitOptions : {}>
  }

  export type TypeMap<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs, GlobalOmitOptions = {}> = {
    globalOmitOptions: {
      omit: GlobalOmitOptions
    }
    meta: {
      modelProps: "pUser" | "pPost" | "pProduct"
      txIsolationLevel: Prisma.TransactionIsolationLevel
    }
    model: {
      PUser: {
        payload: Prisma.$PUserPayload<ExtArgs>
        fields: Prisma.PUserFieldRefs
        operations: {
          findUnique: {
            args: Prisma.PUserFindUniqueArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload> | null
          }
          findUniqueOrThrow: {
            args: Prisma.PUserFindUniqueOrThrowArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload>
          }
          findFirst: {
            args: Prisma.PUserFindFirstArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload> | null
          }
          findFirstOrThrow: {
            args: Prisma.PUserFindFirstOrThrowArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload>
          }
          findMany: {
            args: Prisma.PUserFindManyArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload>[]
          }
          create: {
            args: Prisma.PUserCreateArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload>
          }
          createMany: {
            args: Prisma.PUserCreateManyArgs<ExtArgs>
            result: BatchPayload
          }
          delete: {
            args: Prisma.PUserDeleteArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload>
          }
          update: {
            args: Prisma.PUserUpdateArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload>
          }
          deleteMany: {
            args: Prisma.PUserDeleteManyArgs<ExtArgs>
            result: BatchPayload
          }
          updateMany: {
            args: Prisma.PUserUpdateManyArgs<ExtArgs>
            result: BatchPayload
          }
          upsert: {
            args: Prisma.PUserUpsertArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PUserPayload>
          }
          aggregate: {
            args: Prisma.PUserAggregateArgs<ExtArgs>
            result: $Utils.Optional<AggregatePUser>
          }
          groupBy: {
            args: Prisma.PUserGroupByArgs<ExtArgs>
            result: $Utils.Optional<PUserGroupByOutputType>[]
          }
          count: {
            args: Prisma.PUserCountArgs<ExtArgs>
            result: $Utils.Optional<PUserCountAggregateOutputType> | number
          }
        }
      }
      PPost: {
        payload: Prisma.$PPostPayload<ExtArgs>
        fields: Prisma.PPostFieldRefs
        operations: {
          findUnique: {
            args: Prisma.PPostFindUniqueArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload> | null
          }
          findUniqueOrThrow: {
            args: Prisma.PPostFindUniqueOrThrowArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload>
          }
          findFirst: {
            args: Prisma.PPostFindFirstArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload> | null
          }
          findFirstOrThrow: {
            args: Prisma.PPostFindFirstOrThrowArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload>
          }
          findMany: {
            args: Prisma.PPostFindManyArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload>[]
          }
          create: {
            args: Prisma.PPostCreateArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload>
          }
          createMany: {
            args: Prisma.PPostCreateManyArgs<ExtArgs>
            result: BatchPayload
          }
          delete: {
            args: Prisma.PPostDeleteArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload>
          }
          update: {
            args: Prisma.PPostUpdateArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload>
          }
          deleteMany: {
            args: Prisma.PPostDeleteManyArgs<ExtArgs>
            result: BatchPayload
          }
          updateMany: {
            args: Prisma.PPostUpdateManyArgs<ExtArgs>
            result: BatchPayload
          }
          upsert: {
            args: Prisma.PPostUpsertArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PPostPayload>
          }
          aggregate: {
            args: Prisma.PPostAggregateArgs<ExtArgs>
            result: $Utils.Optional<AggregatePPost>
          }
          groupBy: {
            args: Prisma.PPostGroupByArgs<ExtArgs>
            result: $Utils.Optional<PPostGroupByOutputType>[]
          }
          count: {
            args: Prisma.PPostCountArgs<ExtArgs>
            result: $Utils.Optional<PPostCountAggregateOutputType> | number
          }
        }
      }
      PProduct: {
        payload: Prisma.$PProductPayload<ExtArgs>
        fields: Prisma.PProductFieldRefs
        operations: {
          findUnique: {
            args: Prisma.PProductFindUniqueArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload> | null
          }
          findUniqueOrThrow: {
            args: Prisma.PProductFindUniqueOrThrowArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload>
          }
          findFirst: {
            args: Prisma.PProductFindFirstArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload> | null
          }
          findFirstOrThrow: {
            args: Prisma.PProductFindFirstOrThrowArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload>
          }
          findMany: {
            args: Prisma.PProductFindManyArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload>[]
          }
          create: {
            args: Prisma.PProductCreateArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload>
          }
          createMany: {
            args: Prisma.PProductCreateManyArgs<ExtArgs>
            result: BatchPayload
          }
          delete: {
            args: Prisma.PProductDeleteArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload>
          }
          update: {
            args: Prisma.PProductUpdateArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload>
          }
          deleteMany: {
            args: Prisma.PProductDeleteManyArgs<ExtArgs>
            result: BatchPayload
          }
          updateMany: {
            args: Prisma.PProductUpdateManyArgs<ExtArgs>
            result: BatchPayload
          }
          upsert: {
            args: Prisma.PProductUpsertArgs<ExtArgs>
            result: $Utils.PayloadToResult<Prisma.$PProductPayload>
          }
          aggregate: {
            args: Prisma.PProductAggregateArgs<ExtArgs>
            result: $Utils.Optional<AggregatePProduct>
          }
          groupBy: {
            args: Prisma.PProductGroupByArgs<ExtArgs>
            result: $Utils.Optional<PProductGroupByOutputType>[]
          }
          count: {
            args: Prisma.PProductCountArgs<ExtArgs>
            result: $Utils.Optional<PProductCountAggregateOutputType> | number
          }
        }
      }
    }
  } & {
    other: {
      payload: any
      operations: {
        $executeRaw: {
          args: [query: TemplateStringsArray | Prisma.Sql, ...values: any[]],
          result: any
        }
        $executeRawUnsafe: {
          args: [query: string, ...values: any[]],
          result: any
        }
        $queryRaw: {
          args: [query: TemplateStringsArray | Prisma.Sql, ...values: any[]],
          result: any
        }
        $queryRawUnsafe: {
          args: [query: string, ...values: any[]],
          result: any
        }
      }
    }
  }
  export const defineExtension: $Extensions.ExtendsHook<"define", Prisma.TypeMapCb, $Extensions.DefaultArgs>
  export type DefaultPrismaClient = PrismaClient
  export type ErrorFormat = 'pretty' | 'colorless' | 'minimal'
  export interface PrismaClientOptions {
    /**
     * @default "colorless"
     */
    errorFormat?: ErrorFormat
    /**
     * @example
     * ```
     * // Shorthand for `emit: 'stdout'`
     * log: ['query', 'info', 'warn', 'error']
     * 
     * // Emit as events only
     * log: [
     *   { emit: 'event', level: 'query' },
     *   { emit: 'event', level: 'info' },
     *   { emit: 'event', level: 'warn' }
     *   { emit: 'event', level: 'error' }
     * ]
     * 
     * / Emit as events and log to stdout
     * og: [
     *  { emit: 'stdout', level: 'query' },
     *  { emit: 'stdout', level: 'info' },
     *  { emit: 'stdout', level: 'warn' }
     *  { emit: 'stdout', level: 'error' }
     * 
     * ```
     * Read more in our [docs](https://pris.ly/d/logging).
     */
    log?: (LogLevel | LogDefinition)[]
    /**
     * The default values for transactionOptions
     * maxWait ?= 2000
     * timeout ?= 5000
     */
    transactionOptions?: {
      maxWait?: number
      timeout?: number
      isolationLevel?: Prisma.TransactionIsolationLevel
    }
    /**
     * Instance of a Driver Adapter, e.g., like one provided by `@prisma/adapter-planetscale`
     */
    adapter?: runtime.SqlDriverAdapterFactory
    /**
     * Prisma Accelerate URL allowing the client to connect through Accelerate instead of a direct database.
     */
    accelerateUrl?: string
    /**
     * Global configuration for omitting model fields by default.
     * 
     * @example
     * ```
     * const prisma = new PrismaClient({
     *   omit: {
     *     user: {
     *       password: true
     *     }
     *   }
     * })
     * ```
     */
    omit?: Prisma.GlobalOmitConfig
    /**
     * SQL commenter plugins that add metadata to SQL queries as comments.
     * Comments follow the sqlcommenter format: https://google.github.io/sqlcommenter/
     * 
     * @example
     * ```
     * const prisma = new PrismaClient({
     *   adapter,
     *   comments: [
     *     traceContext(),
     *     queryInsights(),
     *   ],
     * })
     * ```
     */
    comments?: runtime.SqlCommenterPlugin[]
  }
  export type GlobalOmitConfig = {
    pUser?: PUserOmit
    pPost?: PPostOmit
    pProduct?: PProductOmit
  }

  /* Types for Logging */
  export type LogLevel = 'info' | 'query' | 'warn' | 'error'
  export type LogDefinition = {
    level: LogLevel
    emit: 'stdout' | 'event'
  }

  export type CheckIsLogLevel<T> = T extends LogLevel ? T : never;

  export type GetLogType<T> = CheckIsLogLevel<
    T extends LogDefinition ? T['level'] : T
  >;

  export type GetEvents<T extends any[]> = T extends Array<LogLevel | LogDefinition>
    ? GetLogType<T[number]>
    : never;

  export type QueryEvent = {
    timestamp: Date
    query: string
    params: string
    duration: number
    target: string
  }

  export type LogEvent = {
    timestamp: Date
    message: string
    target: string
  }
  /* End Types for Logging */


  export type PrismaAction =
    | 'findUnique'
    | 'findUniqueOrThrow'
    | 'findMany'
    | 'findFirst'
    | 'findFirstOrThrow'
    | 'create'
    | 'createMany'
    | 'createManyAndReturn'
    | 'update'
    | 'updateMany'
    | 'updateManyAndReturn'
    | 'upsert'
    | 'delete'
    | 'deleteMany'
    | 'executeRaw'
    | 'queryRaw'
    | 'aggregate'
    | 'count'
    | 'runCommandRaw'
    | 'findRaw'
    | 'groupBy'

  // tested in getLogLevel.test.ts
  export function getLogLevel(log: Array<LogLevel | LogDefinition>): LogLevel | undefined;

  /**
   * `PrismaClient` proxy available in interactive transactions.
   */
  export type TransactionClient = Omit<Prisma.DefaultPrismaClient, runtime.ITXClientDenyList>

  export type Datasource = {
    url?: string
  }

  /**
   * Count Types
   */


  /**
   * Count Type PUserCountOutputType
   */

  export type PUserCountOutputType = {
    posts: number
  }

  export type PUserCountOutputTypeSelect<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    posts?: boolean | PUserCountOutputTypeCountPostsArgs
  }

  // Custom InputTypes
  /**
   * PUserCountOutputType without action
   */
  export type PUserCountOutputTypeDefaultArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUserCountOutputType
     */
    select?: PUserCountOutputTypeSelect<ExtArgs> | null
  }

  /**
   * PUserCountOutputType without action
   */
  export type PUserCountOutputTypeCountPostsArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    where?: PPostWhereInput
  }


  /**
   * Models
   */

  /**
   * Model PUser
   */

  export type AggregatePUser = {
    _count: PUserCountAggregateOutputType | null
    _avg: PUserAvgAggregateOutputType | null
    _sum: PUserSumAggregateOutputType | null
    _min: PUserMinAggregateOutputType | null
    _max: PUserMaxAggregateOutputType | null
  }

  export type PUserAvgAggregateOutputType = {
    id: number | null
    age: number | null
  }

  export type PUserSumAggregateOutputType = {
    id: number | null
    age: number | null
  }

  export type PUserMinAggregateOutputType = {
    id: number | null
    email: string | null
    name: string | null
    age: number | null
  }

  export type PUserMaxAggregateOutputType = {
    id: number | null
    email: string | null
    name: string | null
    age: number | null
  }

  export type PUserCountAggregateOutputType = {
    id: number
    email: number
    name: number
    age: number
    _all: number
  }


  export type PUserAvgAggregateInputType = {
    id?: true
    age?: true
  }

  export type PUserSumAggregateInputType = {
    id?: true
    age?: true
  }

  export type PUserMinAggregateInputType = {
    id?: true
    email?: true
    name?: true
    age?: true
  }

  export type PUserMaxAggregateInputType = {
    id?: true
    email?: true
    name?: true
    age?: true
  }

  export type PUserCountAggregateInputType = {
    id?: true
    email?: true
    name?: true
    age?: true
    _all?: true
  }

  export type PUserAggregateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Filter which PUser to aggregate.
     */
    where?: PUserWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PUsers to fetch.
     */
    orderBy?: PUserOrderByWithRelationInput | PUserOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the start position
     */
    cursor?: PUserWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PUsers from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PUsers.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Count returned PUsers
    **/
    _count?: true | PUserCountAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to average
    **/
    _avg?: PUserAvgAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to sum
    **/
    _sum?: PUserSumAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to find the minimum value
    **/
    _min?: PUserMinAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to find the maximum value
    **/
    _max?: PUserMaxAggregateInputType
  }

  export type GetPUserAggregateType<T extends PUserAggregateArgs> = {
        [P in keyof T & keyof AggregatePUser]: P extends '_count' | 'count'
      ? T[P] extends true
        ? number
        : GetScalarType<T[P], AggregatePUser[P]>
      : GetScalarType<T[P], AggregatePUser[P]>
  }




  export type PUserGroupByArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    where?: PUserWhereInput
    orderBy?: PUserOrderByWithAggregationInput | PUserOrderByWithAggregationInput[]
    by: PUserScalarFieldEnum[] | PUserScalarFieldEnum
    having?: PUserScalarWhereWithAggregatesInput
    take?: number
    skip?: number
    _count?: PUserCountAggregateInputType | true
    _avg?: PUserAvgAggregateInputType
    _sum?: PUserSumAggregateInputType
    _min?: PUserMinAggregateInputType
    _max?: PUserMaxAggregateInputType
  }

  export type PUserGroupByOutputType = {
    id: number
    email: string
    name: string | null
    age: number | null
    _count: PUserCountAggregateOutputType | null
    _avg: PUserAvgAggregateOutputType | null
    _sum: PUserSumAggregateOutputType | null
    _min: PUserMinAggregateOutputType | null
    _max: PUserMaxAggregateOutputType | null
  }

  type GetPUserGroupByPayload<T extends PUserGroupByArgs> = Prisma.PrismaPromise<
    Array<
      PickEnumerable<PUserGroupByOutputType, T['by']> &
        {
          [P in ((keyof T) & (keyof PUserGroupByOutputType))]: P extends '_count'
            ? T[P] extends boolean
              ? number
              : GetScalarType<T[P], PUserGroupByOutputType[P]>
            : GetScalarType<T[P], PUserGroupByOutputType[P]>
        }
      >
    >


  export type PUserSelect<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = $Extensions.GetSelect<{
    id?: boolean
    email?: boolean
    name?: boolean
    age?: boolean
    posts?: boolean | PUser$postsArgs<ExtArgs>
    _count?: boolean | PUserCountOutputTypeDefaultArgs<ExtArgs>
  }, ExtArgs["result"]["pUser"]>



  export type PUserSelectScalar = {
    id?: boolean
    email?: boolean
    name?: boolean
    age?: boolean
  }

  export type PUserOmit<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = $Extensions.GetOmit<"id" | "email" | "name" | "age", ExtArgs["result"]["pUser"]>
  export type PUserInclude<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    posts?: boolean | PUser$postsArgs<ExtArgs>
    _count?: boolean | PUserCountOutputTypeDefaultArgs<ExtArgs>
  }

  export type $PUserPayload<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    name: "PUser"
    objects: {
      posts: Prisma.$PPostPayload<ExtArgs>[]
    }
    scalars: $Extensions.GetPayloadResult<{
      id: number
      email: string
      name: string | null
      age: number | null
    }, ExtArgs["result"]["pUser"]>
    composites: {}
  }

  type PUserGetPayload<S extends boolean | null | undefined | PUserDefaultArgs> = $Result.GetResult<Prisma.$PUserPayload, S>

  type PUserCountArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> =
    Omit<PUserFindManyArgs, 'select' | 'include' | 'distinct' | 'omit'> & {
      select?: PUserCountAggregateInputType | true
    }

  export interface PUserDelegate<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs, GlobalOmitOptions = {}> {
    [K: symbol]: { types: Prisma.TypeMap<ExtArgs>['model']['PUser'], meta: { name: 'PUser' } }
    /**
     * Find zero or one PUser that matches the filter.
     * @param {PUserFindUniqueArgs} args - Arguments to find a PUser
     * @example
     * // Get one PUser
     * const pUser = await prisma.pUser.findUnique({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findUnique<T extends PUserFindUniqueArgs>(args: SelectSubset<T, PUserFindUniqueArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "findUnique", GlobalOmitOptions> | null, null, ExtArgs, GlobalOmitOptions>

    /**
     * Find one PUser that matches the filter or throw an error with `error.code='P2025'`
     * if no matches were found.
     * @param {PUserFindUniqueOrThrowArgs} args - Arguments to find a PUser
     * @example
     * // Get one PUser
     * const pUser = await prisma.pUser.findUniqueOrThrow({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findUniqueOrThrow<T extends PUserFindUniqueOrThrowArgs>(args: SelectSubset<T, PUserFindUniqueOrThrowArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "findUniqueOrThrow", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Find the first PUser that matches the filter.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PUserFindFirstArgs} args - Arguments to find a PUser
     * @example
     * // Get one PUser
     * const pUser = await prisma.pUser.findFirst({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findFirst<T extends PUserFindFirstArgs>(args?: SelectSubset<T, PUserFindFirstArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "findFirst", GlobalOmitOptions> | null, null, ExtArgs, GlobalOmitOptions>

    /**
     * Find the first PUser that matches the filter or
     * throw `PrismaKnownClientError` with `P2025` code if no matches were found.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PUserFindFirstOrThrowArgs} args - Arguments to find a PUser
     * @example
     * // Get one PUser
     * const pUser = await prisma.pUser.findFirstOrThrow({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findFirstOrThrow<T extends PUserFindFirstOrThrowArgs>(args?: SelectSubset<T, PUserFindFirstOrThrowArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "findFirstOrThrow", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Find zero or more PUsers that matches the filter.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PUserFindManyArgs} args - Arguments to filter and select certain fields only.
     * @example
     * // Get all PUsers
     * const pUsers = await prisma.pUser.findMany()
     * 
     * // Get first 10 PUsers
     * const pUsers = await prisma.pUser.findMany({ take: 10 })
     * 
     * // Only select the `id`
     * const pUserWithIdOnly = await prisma.pUser.findMany({ select: { id: true } })
     * 
     */
    findMany<T extends PUserFindManyArgs>(args?: SelectSubset<T, PUserFindManyArgs<ExtArgs>>): Prisma.PrismaPromise<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "findMany", GlobalOmitOptions>>

    /**
     * Create a PUser.
     * @param {PUserCreateArgs} args - Arguments to create a PUser.
     * @example
     * // Create one PUser
     * const PUser = await prisma.pUser.create({
     *   data: {
     *     // ... data to create a PUser
     *   }
     * })
     * 
     */
    create<T extends PUserCreateArgs>(args: SelectSubset<T, PUserCreateArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "create", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Create many PUsers.
     * @param {PUserCreateManyArgs} args - Arguments to create many PUsers.
     * @example
     * // Create many PUsers
     * const pUser = await prisma.pUser.createMany({
     *   data: [
     *     // ... provide data here
     *   ]
     * })
     *     
     */
    createMany<T extends PUserCreateManyArgs>(args?: SelectSubset<T, PUserCreateManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Delete a PUser.
     * @param {PUserDeleteArgs} args - Arguments to delete one PUser.
     * @example
     * // Delete one PUser
     * const PUser = await prisma.pUser.delete({
     *   where: {
     *     // ... filter to delete one PUser
     *   }
     * })
     * 
     */
    delete<T extends PUserDeleteArgs>(args: SelectSubset<T, PUserDeleteArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "delete", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Update one PUser.
     * @param {PUserUpdateArgs} args - Arguments to update one PUser.
     * @example
     * // Update one PUser
     * const pUser = await prisma.pUser.update({
     *   where: {
     *     // ... provide filter here
     *   },
     *   data: {
     *     // ... provide data here
     *   }
     * })
     * 
     */
    update<T extends PUserUpdateArgs>(args: SelectSubset<T, PUserUpdateArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "update", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Delete zero or more PUsers.
     * @param {PUserDeleteManyArgs} args - Arguments to filter PUsers to delete.
     * @example
     * // Delete a few PUsers
     * const { count } = await prisma.pUser.deleteMany({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     * 
     */
    deleteMany<T extends PUserDeleteManyArgs>(args?: SelectSubset<T, PUserDeleteManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Update zero or more PUsers.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PUserUpdateManyArgs} args - Arguments to update one or more rows.
     * @example
     * // Update many PUsers
     * const pUser = await prisma.pUser.updateMany({
     *   where: {
     *     // ... provide filter here
     *   },
     *   data: {
     *     // ... provide data here
     *   }
     * })
     * 
     */
    updateMany<T extends PUserUpdateManyArgs>(args: SelectSubset<T, PUserUpdateManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Create or update one PUser.
     * @param {PUserUpsertArgs} args - Arguments to update or create a PUser.
     * @example
     * // Update or create a PUser
     * const pUser = await prisma.pUser.upsert({
     *   create: {
     *     // ... data to create a PUser
     *   },
     *   update: {
     *     // ... in case it already exists, update
     *   },
     *   where: {
     *     // ... the filter for the PUser we want to update
     *   }
     * })
     */
    upsert<T extends PUserUpsertArgs>(args: SelectSubset<T, PUserUpsertArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "upsert", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>


    /**
     * Count the number of PUsers.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PUserCountArgs} args - Arguments to filter PUsers to count.
     * @example
     * // Count the number of PUsers
     * const count = await prisma.pUser.count({
     *   where: {
     *     // ... the filter for the PUsers we want to count
     *   }
     * })
    **/
    count<T extends PUserCountArgs>(
      args?: Subset<T, PUserCountArgs>,
    ): Prisma.PrismaPromise<
      T extends $Utils.Record<'select', any>
        ? T['select'] extends true
          ? number
          : GetScalarType<T['select'], PUserCountAggregateOutputType>
        : number
    >

    /**
     * Allows you to perform aggregations operations on a PUser.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PUserAggregateArgs} args - Select which aggregations you would like to apply and on what fields.
     * @example
     * // Ordered by age ascending
     * // Where email contains prisma.io
     * // Limited to the 10 users
     * const aggregations = await prisma.user.aggregate({
     *   _avg: {
     *     age: true,
     *   },
     *   where: {
     *     email: {
     *       contains: "prisma.io",
     *     },
     *   },
     *   orderBy: {
     *     age: "asc",
     *   },
     *   take: 10,
     * })
    **/
    aggregate<T extends PUserAggregateArgs>(args: Subset<T, PUserAggregateArgs>): Prisma.PrismaPromise<GetPUserAggregateType<T>>

    /**
     * Group by PUser.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PUserGroupByArgs} args - Group by arguments.
     * @example
     * // Group by city, order by createdAt, get count
     * const result = await prisma.user.groupBy({
     *   by: ['city', 'createdAt'],
     *   orderBy: {
     *     createdAt: true
     *   },
     *   _count: {
     *     _all: true
     *   },
     * })
     * 
    **/
    groupBy<
      T extends PUserGroupByArgs,
      HasSelectOrTake extends Or<
        Extends<'skip', Keys<T>>,
        Extends<'take', Keys<T>>
      >,
      OrderByArg extends True extends HasSelectOrTake
        ? { orderBy: PUserGroupByArgs['orderBy'] }
        : { orderBy?: PUserGroupByArgs['orderBy'] },
      OrderFields extends ExcludeUnderscoreKeys<Keys<MaybeTupleToUnion<T['orderBy']>>>,
      ByFields extends MaybeTupleToUnion<T['by']>,
      ByValid extends Has<ByFields, OrderFields>,
      HavingFields extends GetHavingFields<T['having']>,
      HavingValid extends Has<ByFields, HavingFields>,
      ByEmpty extends T['by'] extends never[] ? True : False,
      InputErrors extends ByEmpty extends True
      ? `Error: "by" must not be empty.`
      : HavingValid extends False
      ? {
          [P in HavingFields]: P extends ByFields
            ? never
            : P extends string
            ? `Error: Field "${P}" used in "having" needs to be provided in "by".`
            : [
                Error,
                'Field ',
                P,
                ` in "having" needs to be provided in "by"`,
              ]
        }[HavingFields]
      : 'take' extends Keys<T>
      ? 'orderBy' extends Keys<T>
        ? ByValid extends True
          ? {}
          : {
              [P in OrderFields]: P extends ByFields
                ? never
                : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
            }[OrderFields]
        : 'Error: If you provide "take", you also need to provide "orderBy"'
      : 'skip' extends Keys<T>
      ? 'orderBy' extends Keys<T>
        ? ByValid extends True
          ? {}
          : {
              [P in OrderFields]: P extends ByFields
                ? never
                : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
            }[OrderFields]
        : 'Error: If you provide "skip", you also need to provide "orderBy"'
      : ByValid extends True
      ? {}
      : {
          [P in OrderFields]: P extends ByFields
            ? never
            : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
        }[OrderFields]
    >(args: SubsetIntersection<T, PUserGroupByArgs, OrderByArg> & InputErrors): {} extends InputErrors ? GetPUserGroupByPayload<T> : Prisma.PrismaPromise<InputErrors>
  /**
   * Fields of the PUser model
   */
  readonly fields: PUserFieldRefs;
  }

  /**
   * The delegate class that acts as a "Promise-like" for PUser.
   * Why is this prefixed with `Prisma__`?
   * Because we want to prevent naming conflicts as mentioned in
   * https://github.com/prisma/prisma-client-js/issues/707
   */
  export interface Prisma__PUserClient<T, Null = never, ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs, GlobalOmitOptions = {}> extends Prisma.PrismaPromise<T> {
    readonly [Symbol.toStringTag]: "PrismaPromise"
    posts<T extends PUser$postsArgs<ExtArgs> = {}>(args?: Subset<T, PUser$postsArgs<ExtArgs>>): Prisma.PrismaPromise<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "findMany", GlobalOmitOptions> | Null>
    /**
     * Attaches callbacks for the resolution and/or rejection of the Promise.
     * @param onfulfilled The callback to execute when the Promise is resolved.
     * @param onrejected The callback to execute when the Promise is rejected.
     * @returns A Promise for the completion of which ever callback is executed.
     */
    then<TResult1 = T, TResult2 = never>(onfulfilled?: ((value: T) => TResult1 | PromiseLike<TResult1>) | undefined | null, onrejected?: ((reason: any) => TResult2 | PromiseLike<TResult2>) | undefined | null): $Utils.JsPromise<TResult1 | TResult2>
    /**
     * Attaches a callback for only the rejection of the Promise.
     * @param onrejected The callback to execute when the Promise is rejected.
     * @returns A Promise for the completion of the callback.
     */
    catch<TResult = never>(onrejected?: ((reason: any) => TResult | PromiseLike<TResult>) | undefined | null): $Utils.JsPromise<T | TResult>
    /**
     * Attaches a callback that is invoked when the Promise is settled (fulfilled or rejected). The
     * resolved value cannot be modified from the callback.
     * @param onfinally The callback to execute when the Promise is settled (fulfilled or rejected).
     * @returns A Promise for the completion of the callback.
     */
    finally(onfinally?: (() => void) | undefined | null): $Utils.JsPromise<T>
  }




  /**
   * Fields of the PUser model
   */
  interface PUserFieldRefs {
    readonly id: FieldRef<"PUser", 'Int'>
    readonly email: FieldRef<"PUser", 'String'>
    readonly name: FieldRef<"PUser", 'String'>
    readonly age: FieldRef<"PUser", 'Int'>
  }
    

  // Custom InputTypes
  /**
   * PUser findUnique
   */
  export type PUserFindUniqueArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * Filter, which PUser to fetch.
     */
    where: PUserWhereUniqueInput
  }

  /**
   * PUser findUniqueOrThrow
   */
  export type PUserFindUniqueOrThrowArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * Filter, which PUser to fetch.
     */
    where: PUserWhereUniqueInput
  }

  /**
   * PUser findFirst
   */
  export type PUserFindFirstArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * Filter, which PUser to fetch.
     */
    where?: PUserWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PUsers to fetch.
     */
    orderBy?: PUserOrderByWithRelationInput | PUserOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for searching for PUsers.
     */
    cursor?: PUserWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PUsers from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PUsers.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PUsers.
     */
    distinct?: PUserScalarFieldEnum | PUserScalarFieldEnum[]
  }

  /**
   * PUser findFirstOrThrow
   */
  export type PUserFindFirstOrThrowArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * Filter, which PUser to fetch.
     */
    where?: PUserWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PUsers to fetch.
     */
    orderBy?: PUserOrderByWithRelationInput | PUserOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for searching for PUsers.
     */
    cursor?: PUserWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PUsers from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PUsers.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PUsers.
     */
    distinct?: PUserScalarFieldEnum | PUserScalarFieldEnum[]
  }

  /**
   * PUser findMany
   */
  export type PUserFindManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * Filter, which PUsers to fetch.
     */
    where?: PUserWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PUsers to fetch.
     */
    orderBy?: PUserOrderByWithRelationInput | PUserOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for listing PUsers.
     */
    cursor?: PUserWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PUsers from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PUsers.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PUsers.
     */
    distinct?: PUserScalarFieldEnum | PUserScalarFieldEnum[]
  }

  /**
   * PUser create
   */
  export type PUserCreateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * The data needed to create a PUser.
     */
    data: XOR<PUserCreateInput, PUserUncheckedCreateInput>
  }

  /**
   * PUser createMany
   */
  export type PUserCreateManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * The data used to create many PUsers.
     */
    data: PUserCreateManyInput | PUserCreateManyInput[]
    skipDuplicates?: boolean
  }

  /**
   * PUser update
   */
  export type PUserUpdateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * The data needed to update a PUser.
     */
    data: XOR<PUserUpdateInput, PUserUncheckedUpdateInput>
    /**
     * Choose, which PUser to update.
     */
    where: PUserWhereUniqueInput
  }

  /**
   * PUser updateMany
   */
  export type PUserUpdateManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * The data used to update PUsers.
     */
    data: XOR<PUserUpdateManyMutationInput, PUserUncheckedUpdateManyInput>
    /**
     * Filter which PUsers to update
     */
    where?: PUserWhereInput
    /**
     * Limit how many PUsers to update.
     */
    limit?: number
  }

  /**
   * PUser upsert
   */
  export type PUserUpsertArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * The filter to search for the PUser to update in case it exists.
     */
    where: PUserWhereUniqueInput
    /**
     * In case the PUser found by the `where` argument doesn't exist, create a new PUser with this data.
     */
    create: XOR<PUserCreateInput, PUserUncheckedCreateInput>
    /**
     * In case the PUser was found with the provided `where` argument, update it with this data.
     */
    update: XOR<PUserUpdateInput, PUserUncheckedUpdateInput>
  }

  /**
   * PUser delete
   */
  export type PUserDeleteArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
    /**
     * Filter which PUser to delete.
     */
    where: PUserWhereUniqueInput
  }

  /**
   * PUser deleteMany
   */
  export type PUserDeleteManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Filter which PUsers to delete
     */
    where?: PUserWhereInput
    /**
     * Limit how many PUsers to delete.
     */
    limit?: number
  }

  /**
   * PUser.posts
   */
  export type PUser$postsArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    where?: PPostWhereInput
    orderBy?: PPostOrderByWithRelationInput | PPostOrderByWithRelationInput[]
    cursor?: PPostWhereUniqueInput
    take?: number
    skip?: number
    distinct?: PPostScalarFieldEnum | PPostScalarFieldEnum[]
  }

  /**
   * PUser without action
   */
  export type PUserDefaultArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PUser
     */
    select?: PUserSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PUser
     */
    omit?: PUserOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PUserInclude<ExtArgs> | null
  }


  /**
   * Model PPost
   */

  export type AggregatePPost = {
    _count: PPostCountAggregateOutputType | null
    _avg: PPostAvgAggregateOutputType | null
    _sum: PPostSumAggregateOutputType | null
    _min: PPostMinAggregateOutputType | null
    _max: PPostMaxAggregateOutputType | null
  }

  export type PPostAvgAggregateOutputType = {
    id: number | null
    views: number | null
    authorId: number | null
  }

  export type PPostSumAggregateOutputType = {
    id: number | null
    views: number | null
    authorId: number | null
  }

  export type PPostMinAggregateOutputType = {
    id: number | null
    title: string | null
    body: string | null
    published: boolean | null
    views: number | null
    authorId: number | null
  }

  export type PPostMaxAggregateOutputType = {
    id: number | null
    title: string | null
    body: string | null
    published: boolean | null
    views: number | null
    authorId: number | null
  }

  export type PPostCountAggregateOutputType = {
    id: number
    title: number
    body: number
    published: number
    views: number
    authorId: number
    _all: number
  }


  export type PPostAvgAggregateInputType = {
    id?: true
    views?: true
    authorId?: true
  }

  export type PPostSumAggregateInputType = {
    id?: true
    views?: true
    authorId?: true
  }

  export type PPostMinAggregateInputType = {
    id?: true
    title?: true
    body?: true
    published?: true
    views?: true
    authorId?: true
  }

  export type PPostMaxAggregateInputType = {
    id?: true
    title?: true
    body?: true
    published?: true
    views?: true
    authorId?: true
  }

  export type PPostCountAggregateInputType = {
    id?: true
    title?: true
    body?: true
    published?: true
    views?: true
    authorId?: true
    _all?: true
  }

  export type PPostAggregateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Filter which PPost to aggregate.
     */
    where?: PPostWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PPosts to fetch.
     */
    orderBy?: PPostOrderByWithRelationInput | PPostOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the start position
     */
    cursor?: PPostWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PPosts from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PPosts.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Count returned PPosts
    **/
    _count?: true | PPostCountAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to average
    **/
    _avg?: PPostAvgAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to sum
    **/
    _sum?: PPostSumAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to find the minimum value
    **/
    _min?: PPostMinAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to find the maximum value
    **/
    _max?: PPostMaxAggregateInputType
  }

  export type GetPPostAggregateType<T extends PPostAggregateArgs> = {
        [P in keyof T & keyof AggregatePPost]: P extends '_count' | 'count'
      ? T[P] extends true
        ? number
        : GetScalarType<T[P], AggregatePPost[P]>
      : GetScalarType<T[P], AggregatePPost[P]>
  }




  export type PPostGroupByArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    where?: PPostWhereInput
    orderBy?: PPostOrderByWithAggregationInput | PPostOrderByWithAggregationInput[]
    by: PPostScalarFieldEnum[] | PPostScalarFieldEnum
    having?: PPostScalarWhereWithAggregatesInput
    take?: number
    skip?: number
    _count?: PPostCountAggregateInputType | true
    _avg?: PPostAvgAggregateInputType
    _sum?: PPostSumAggregateInputType
    _min?: PPostMinAggregateInputType
    _max?: PPostMaxAggregateInputType
  }

  export type PPostGroupByOutputType = {
    id: number
    title: string
    body: string | null
    published: boolean
    views: number
    authorId: number
    _count: PPostCountAggregateOutputType | null
    _avg: PPostAvgAggregateOutputType | null
    _sum: PPostSumAggregateOutputType | null
    _min: PPostMinAggregateOutputType | null
    _max: PPostMaxAggregateOutputType | null
  }

  type GetPPostGroupByPayload<T extends PPostGroupByArgs> = Prisma.PrismaPromise<
    Array<
      PickEnumerable<PPostGroupByOutputType, T['by']> &
        {
          [P in ((keyof T) & (keyof PPostGroupByOutputType))]: P extends '_count'
            ? T[P] extends boolean
              ? number
              : GetScalarType<T[P], PPostGroupByOutputType[P]>
            : GetScalarType<T[P], PPostGroupByOutputType[P]>
        }
      >
    >


  export type PPostSelect<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = $Extensions.GetSelect<{
    id?: boolean
    title?: boolean
    body?: boolean
    published?: boolean
    views?: boolean
    authorId?: boolean
    author?: boolean | PUserDefaultArgs<ExtArgs>
  }, ExtArgs["result"]["pPost"]>



  export type PPostSelectScalar = {
    id?: boolean
    title?: boolean
    body?: boolean
    published?: boolean
    views?: boolean
    authorId?: boolean
  }

  export type PPostOmit<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = $Extensions.GetOmit<"id" | "title" | "body" | "published" | "views" | "authorId", ExtArgs["result"]["pPost"]>
  export type PPostInclude<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    author?: boolean | PUserDefaultArgs<ExtArgs>
  }

  export type $PPostPayload<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    name: "PPost"
    objects: {
      author: Prisma.$PUserPayload<ExtArgs>
    }
    scalars: $Extensions.GetPayloadResult<{
      id: number
      title: string
      body: string | null
      published: boolean
      views: number
      authorId: number
    }, ExtArgs["result"]["pPost"]>
    composites: {}
  }

  type PPostGetPayload<S extends boolean | null | undefined | PPostDefaultArgs> = $Result.GetResult<Prisma.$PPostPayload, S>

  type PPostCountArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> =
    Omit<PPostFindManyArgs, 'select' | 'include' | 'distinct' | 'omit'> & {
      select?: PPostCountAggregateInputType | true
    }

  export interface PPostDelegate<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs, GlobalOmitOptions = {}> {
    [K: symbol]: { types: Prisma.TypeMap<ExtArgs>['model']['PPost'], meta: { name: 'PPost' } }
    /**
     * Find zero or one PPost that matches the filter.
     * @param {PPostFindUniqueArgs} args - Arguments to find a PPost
     * @example
     * // Get one PPost
     * const pPost = await prisma.pPost.findUnique({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findUnique<T extends PPostFindUniqueArgs>(args: SelectSubset<T, PPostFindUniqueArgs<ExtArgs>>): Prisma__PPostClient<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "findUnique", GlobalOmitOptions> | null, null, ExtArgs, GlobalOmitOptions>

    /**
     * Find one PPost that matches the filter or throw an error with `error.code='P2025'`
     * if no matches were found.
     * @param {PPostFindUniqueOrThrowArgs} args - Arguments to find a PPost
     * @example
     * // Get one PPost
     * const pPost = await prisma.pPost.findUniqueOrThrow({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findUniqueOrThrow<T extends PPostFindUniqueOrThrowArgs>(args: SelectSubset<T, PPostFindUniqueOrThrowArgs<ExtArgs>>): Prisma__PPostClient<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "findUniqueOrThrow", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Find the first PPost that matches the filter.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PPostFindFirstArgs} args - Arguments to find a PPost
     * @example
     * // Get one PPost
     * const pPost = await prisma.pPost.findFirst({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findFirst<T extends PPostFindFirstArgs>(args?: SelectSubset<T, PPostFindFirstArgs<ExtArgs>>): Prisma__PPostClient<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "findFirst", GlobalOmitOptions> | null, null, ExtArgs, GlobalOmitOptions>

    /**
     * Find the first PPost that matches the filter or
     * throw `PrismaKnownClientError` with `P2025` code if no matches were found.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PPostFindFirstOrThrowArgs} args - Arguments to find a PPost
     * @example
     * // Get one PPost
     * const pPost = await prisma.pPost.findFirstOrThrow({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findFirstOrThrow<T extends PPostFindFirstOrThrowArgs>(args?: SelectSubset<T, PPostFindFirstOrThrowArgs<ExtArgs>>): Prisma__PPostClient<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "findFirstOrThrow", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Find zero or more PPosts that matches the filter.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PPostFindManyArgs} args - Arguments to filter and select certain fields only.
     * @example
     * // Get all PPosts
     * const pPosts = await prisma.pPost.findMany()
     * 
     * // Get first 10 PPosts
     * const pPosts = await prisma.pPost.findMany({ take: 10 })
     * 
     * // Only select the `id`
     * const pPostWithIdOnly = await prisma.pPost.findMany({ select: { id: true } })
     * 
     */
    findMany<T extends PPostFindManyArgs>(args?: SelectSubset<T, PPostFindManyArgs<ExtArgs>>): Prisma.PrismaPromise<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "findMany", GlobalOmitOptions>>

    /**
     * Create a PPost.
     * @param {PPostCreateArgs} args - Arguments to create a PPost.
     * @example
     * // Create one PPost
     * const PPost = await prisma.pPost.create({
     *   data: {
     *     // ... data to create a PPost
     *   }
     * })
     * 
     */
    create<T extends PPostCreateArgs>(args: SelectSubset<T, PPostCreateArgs<ExtArgs>>): Prisma__PPostClient<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "create", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Create many PPosts.
     * @param {PPostCreateManyArgs} args - Arguments to create many PPosts.
     * @example
     * // Create many PPosts
     * const pPost = await prisma.pPost.createMany({
     *   data: [
     *     // ... provide data here
     *   ]
     * })
     *     
     */
    createMany<T extends PPostCreateManyArgs>(args?: SelectSubset<T, PPostCreateManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Delete a PPost.
     * @param {PPostDeleteArgs} args - Arguments to delete one PPost.
     * @example
     * // Delete one PPost
     * const PPost = await prisma.pPost.delete({
     *   where: {
     *     // ... filter to delete one PPost
     *   }
     * })
     * 
     */
    delete<T extends PPostDeleteArgs>(args: SelectSubset<T, PPostDeleteArgs<ExtArgs>>): Prisma__PPostClient<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "delete", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Update one PPost.
     * @param {PPostUpdateArgs} args - Arguments to update one PPost.
     * @example
     * // Update one PPost
     * const pPost = await prisma.pPost.update({
     *   where: {
     *     // ... provide filter here
     *   },
     *   data: {
     *     // ... provide data here
     *   }
     * })
     * 
     */
    update<T extends PPostUpdateArgs>(args: SelectSubset<T, PPostUpdateArgs<ExtArgs>>): Prisma__PPostClient<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "update", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Delete zero or more PPosts.
     * @param {PPostDeleteManyArgs} args - Arguments to filter PPosts to delete.
     * @example
     * // Delete a few PPosts
     * const { count } = await prisma.pPost.deleteMany({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     * 
     */
    deleteMany<T extends PPostDeleteManyArgs>(args?: SelectSubset<T, PPostDeleteManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Update zero or more PPosts.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PPostUpdateManyArgs} args - Arguments to update one or more rows.
     * @example
     * // Update many PPosts
     * const pPost = await prisma.pPost.updateMany({
     *   where: {
     *     // ... provide filter here
     *   },
     *   data: {
     *     // ... provide data here
     *   }
     * })
     * 
     */
    updateMany<T extends PPostUpdateManyArgs>(args: SelectSubset<T, PPostUpdateManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Create or update one PPost.
     * @param {PPostUpsertArgs} args - Arguments to update or create a PPost.
     * @example
     * // Update or create a PPost
     * const pPost = await prisma.pPost.upsert({
     *   create: {
     *     // ... data to create a PPost
     *   },
     *   update: {
     *     // ... in case it already exists, update
     *   },
     *   where: {
     *     // ... the filter for the PPost we want to update
     *   }
     * })
     */
    upsert<T extends PPostUpsertArgs>(args: SelectSubset<T, PPostUpsertArgs<ExtArgs>>): Prisma__PPostClient<$Result.GetResult<Prisma.$PPostPayload<ExtArgs>, T, "upsert", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>


    /**
     * Count the number of PPosts.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PPostCountArgs} args - Arguments to filter PPosts to count.
     * @example
     * // Count the number of PPosts
     * const count = await prisma.pPost.count({
     *   where: {
     *     // ... the filter for the PPosts we want to count
     *   }
     * })
    **/
    count<T extends PPostCountArgs>(
      args?: Subset<T, PPostCountArgs>,
    ): Prisma.PrismaPromise<
      T extends $Utils.Record<'select', any>
        ? T['select'] extends true
          ? number
          : GetScalarType<T['select'], PPostCountAggregateOutputType>
        : number
    >

    /**
     * Allows you to perform aggregations operations on a PPost.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PPostAggregateArgs} args - Select which aggregations you would like to apply and on what fields.
     * @example
     * // Ordered by age ascending
     * // Where email contains prisma.io
     * // Limited to the 10 users
     * const aggregations = await prisma.user.aggregate({
     *   _avg: {
     *     age: true,
     *   },
     *   where: {
     *     email: {
     *       contains: "prisma.io",
     *     },
     *   },
     *   orderBy: {
     *     age: "asc",
     *   },
     *   take: 10,
     * })
    **/
    aggregate<T extends PPostAggregateArgs>(args: Subset<T, PPostAggregateArgs>): Prisma.PrismaPromise<GetPPostAggregateType<T>>

    /**
     * Group by PPost.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PPostGroupByArgs} args - Group by arguments.
     * @example
     * // Group by city, order by createdAt, get count
     * const result = await prisma.user.groupBy({
     *   by: ['city', 'createdAt'],
     *   orderBy: {
     *     createdAt: true
     *   },
     *   _count: {
     *     _all: true
     *   },
     * })
     * 
    **/
    groupBy<
      T extends PPostGroupByArgs,
      HasSelectOrTake extends Or<
        Extends<'skip', Keys<T>>,
        Extends<'take', Keys<T>>
      >,
      OrderByArg extends True extends HasSelectOrTake
        ? { orderBy: PPostGroupByArgs['orderBy'] }
        : { orderBy?: PPostGroupByArgs['orderBy'] },
      OrderFields extends ExcludeUnderscoreKeys<Keys<MaybeTupleToUnion<T['orderBy']>>>,
      ByFields extends MaybeTupleToUnion<T['by']>,
      ByValid extends Has<ByFields, OrderFields>,
      HavingFields extends GetHavingFields<T['having']>,
      HavingValid extends Has<ByFields, HavingFields>,
      ByEmpty extends T['by'] extends never[] ? True : False,
      InputErrors extends ByEmpty extends True
      ? `Error: "by" must not be empty.`
      : HavingValid extends False
      ? {
          [P in HavingFields]: P extends ByFields
            ? never
            : P extends string
            ? `Error: Field "${P}" used in "having" needs to be provided in "by".`
            : [
                Error,
                'Field ',
                P,
                ` in "having" needs to be provided in "by"`,
              ]
        }[HavingFields]
      : 'take' extends Keys<T>
      ? 'orderBy' extends Keys<T>
        ? ByValid extends True
          ? {}
          : {
              [P in OrderFields]: P extends ByFields
                ? never
                : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
            }[OrderFields]
        : 'Error: If you provide "take", you also need to provide "orderBy"'
      : 'skip' extends Keys<T>
      ? 'orderBy' extends Keys<T>
        ? ByValid extends True
          ? {}
          : {
              [P in OrderFields]: P extends ByFields
                ? never
                : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
            }[OrderFields]
        : 'Error: If you provide "skip", you also need to provide "orderBy"'
      : ByValid extends True
      ? {}
      : {
          [P in OrderFields]: P extends ByFields
            ? never
            : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
        }[OrderFields]
    >(args: SubsetIntersection<T, PPostGroupByArgs, OrderByArg> & InputErrors): {} extends InputErrors ? GetPPostGroupByPayload<T> : Prisma.PrismaPromise<InputErrors>
  /**
   * Fields of the PPost model
   */
  readonly fields: PPostFieldRefs;
  }

  /**
   * The delegate class that acts as a "Promise-like" for PPost.
   * Why is this prefixed with `Prisma__`?
   * Because we want to prevent naming conflicts as mentioned in
   * https://github.com/prisma/prisma-client-js/issues/707
   */
  export interface Prisma__PPostClient<T, Null = never, ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs, GlobalOmitOptions = {}> extends Prisma.PrismaPromise<T> {
    readonly [Symbol.toStringTag]: "PrismaPromise"
    author<T extends PUserDefaultArgs<ExtArgs> = {}>(args?: Subset<T, PUserDefaultArgs<ExtArgs>>): Prisma__PUserClient<$Result.GetResult<Prisma.$PUserPayload<ExtArgs>, T, "findUniqueOrThrow", GlobalOmitOptions> | Null, Null, ExtArgs, GlobalOmitOptions>
    /**
     * Attaches callbacks for the resolution and/or rejection of the Promise.
     * @param onfulfilled The callback to execute when the Promise is resolved.
     * @param onrejected The callback to execute when the Promise is rejected.
     * @returns A Promise for the completion of which ever callback is executed.
     */
    then<TResult1 = T, TResult2 = never>(onfulfilled?: ((value: T) => TResult1 | PromiseLike<TResult1>) | undefined | null, onrejected?: ((reason: any) => TResult2 | PromiseLike<TResult2>) | undefined | null): $Utils.JsPromise<TResult1 | TResult2>
    /**
     * Attaches a callback for only the rejection of the Promise.
     * @param onrejected The callback to execute when the Promise is rejected.
     * @returns A Promise for the completion of the callback.
     */
    catch<TResult = never>(onrejected?: ((reason: any) => TResult | PromiseLike<TResult>) | undefined | null): $Utils.JsPromise<T | TResult>
    /**
     * Attaches a callback that is invoked when the Promise is settled (fulfilled or rejected). The
     * resolved value cannot be modified from the callback.
     * @param onfinally The callback to execute when the Promise is settled (fulfilled or rejected).
     * @returns A Promise for the completion of the callback.
     */
    finally(onfinally?: (() => void) | undefined | null): $Utils.JsPromise<T>
  }




  /**
   * Fields of the PPost model
   */
  interface PPostFieldRefs {
    readonly id: FieldRef<"PPost", 'Int'>
    readonly title: FieldRef<"PPost", 'String'>
    readonly body: FieldRef<"PPost", 'String'>
    readonly published: FieldRef<"PPost", 'Boolean'>
    readonly views: FieldRef<"PPost", 'Int'>
    readonly authorId: FieldRef<"PPost", 'Int'>
  }
    

  // Custom InputTypes
  /**
   * PPost findUnique
   */
  export type PPostFindUniqueArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * Filter, which PPost to fetch.
     */
    where: PPostWhereUniqueInput
  }

  /**
   * PPost findUniqueOrThrow
   */
  export type PPostFindUniqueOrThrowArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * Filter, which PPost to fetch.
     */
    where: PPostWhereUniqueInput
  }

  /**
   * PPost findFirst
   */
  export type PPostFindFirstArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * Filter, which PPost to fetch.
     */
    where?: PPostWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PPosts to fetch.
     */
    orderBy?: PPostOrderByWithRelationInput | PPostOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for searching for PPosts.
     */
    cursor?: PPostWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PPosts from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PPosts.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PPosts.
     */
    distinct?: PPostScalarFieldEnum | PPostScalarFieldEnum[]
  }

  /**
   * PPost findFirstOrThrow
   */
  export type PPostFindFirstOrThrowArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * Filter, which PPost to fetch.
     */
    where?: PPostWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PPosts to fetch.
     */
    orderBy?: PPostOrderByWithRelationInput | PPostOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for searching for PPosts.
     */
    cursor?: PPostWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PPosts from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PPosts.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PPosts.
     */
    distinct?: PPostScalarFieldEnum | PPostScalarFieldEnum[]
  }

  /**
   * PPost findMany
   */
  export type PPostFindManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * Filter, which PPosts to fetch.
     */
    where?: PPostWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PPosts to fetch.
     */
    orderBy?: PPostOrderByWithRelationInput | PPostOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for listing PPosts.
     */
    cursor?: PPostWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PPosts from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PPosts.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PPosts.
     */
    distinct?: PPostScalarFieldEnum | PPostScalarFieldEnum[]
  }

  /**
   * PPost create
   */
  export type PPostCreateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * The data needed to create a PPost.
     */
    data: XOR<PPostCreateInput, PPostUncheckedCreateInput>
  }

  /**
   * PPost createMany
   */
  export type PPostCreateManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * The data used to create many PPosts.
     */
    data: PPostCreateManyInput | PPostCreateManyInput[]
    skipDuplicates?: boolean
  }

  /**
   * PPost update
   */
  export type PPostUpdateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * The data needed to update a PPost.
     */
    data: XOR<PPostUpdateInput, PPostUncheckedUpdateInput>
    /**
     * Choose, which PPost to update.
     */
    where: PPostWhereUniqueInput
  }

  /**
   * PPost updateMany
   */
  export type PPostUpdateManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * The data used to update PPosts.
     */
    data: XOR<PPostUpdateManyMutationInput, PPostUncheckedUpdateManyInput>
    /**
     * Filter which PPosts to update
     */
    where?: PPostWhereInput
    /**
     * Limit how many PPosts to update.
     */
    limit?: number
  }

  /**
   * PPost upsert
   */
  export type PPostUpsertArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * The filter to search for the PPost to update in case it exists.
     */
    where: PPostWhereUniqueInput
    /**
     * In case the PPost found by the `where` argument doesn't exist, create a new PPost with this data.
     */
    create: XOR<PPostCreateInput, PPostUncheckedCreateInput>
    /**
     * In case the PPost was found with the provided `where` argument, update it with this data.
     */
    update: XOR<PPostUpdateInput, PPostUncheckedUpdateInput>
  }

  /**
   * PPost delete
   */
  export type PPostDeleteArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
    /**
     * Filter which PPost to delete.
     */
    where: PPostWhereUniqueInput
  }

  /**
   * PPost deleteMany
   */
  export type PPostDeleteManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Filter which PPosts to delete
     */
    where?: PPostWhereInput
    /**
     * Limit how many PPosts to delete.
     */
    limit?: number
  }

  /**
   * PPost without action
   */
  export type PPostDefaultArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PPost
     */
    select?: PPostSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PPost
     */
    omit?: PPostOmit<ExtArgs> | null
    /**
     * Choose, which related nodes to fetch as well
     */
    include?: PPostInclude<ExtArgs> | null
  }


  /**
   * Model PProduct
   */

  export type AggregatePProduct = {
    _count: PProductCountAggregateOutputType | null
    _avg: PProductAvgAggregateOutputType | null
    _sum: PProductSumAggregateOutputType | null
    _min: PProductMinAggregateOutputType | null
    _max: PProductMaxAggregateOutputType | null
  }

  export type PProductAvgAggregateOutputType = {
    id: number | null
    price: Decimal | null
    stock: number | null
  }

  export type PProductSumAggregateOutputType = {
    id: number | null
    price: Decimal | null
    stock: number | null
  }

  export type PProductMinAggregateOutputType = {
    id: number | null
    sku: string | null
    name: string | null
    price: Decimal | null
    stock: number | null
  }

  export type PProductMaxAggregateOutputType = {
    id: number | null
    sku: string | null
    name: string | null
    price: Decimal | null
    stock: number | null
  }

  export type PProductCountAggregateOutputType = {
    id: number
    sku: number
    name: number
    price: number
    stock: number
    meta: number
    _all: number
  }


  export type PProductAvgAggregateInputType = {
    id?: true
    price?: true
    stock?: true
  }

  export type PProductSumAggregateInputType = {
    id?: true
    price?: true
    stock?: true
  }

  export type PProductMinAggregateInputType = {
    id?: true
    sku?: true
    name?: true
    price?: true
    stock?: true
  }

  export type PProductMaxAggregateInputType = {
    id?: true
    sku?: true
    name?: true
    price?: true
    stock?: true
  }

  export type PProductCountAggregateInputType = {
    id?: true
    sku?: true
    name?: true
    price?: true
    stock?: true
    meta?: true
    _all?: true
  }

  export type PProductAggregateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Filter which PProduct to aggregate.
     */
    where?: PProductWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PProducts to fetch.
     */
    orderBy?: PProductOrderByWithRelationInput | PProductOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the start position
     */
    cursor?: PProductWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PProducts from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PProducts.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Count returned PProducts
    **/
    _count?: true | PProductCountAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to average
    **/
    _avg?: PProductAvgAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to sum
    **/
    _sum?: PProductSumAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to find the minimum value
    **/
    _min?: PProductMinAggregateInputType
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/aggregations Aggregation Docs}
     * 
     * Select which fields to find the maximum value
    **/
    _max?: PProductMaxAggregateInputType
  }

  export type GetPProductAggregateType<T extends PProductAggregateArgs> = {
        [P in keyof T & keyof AggregatePProduct]: P extends '_count' | 'count'
      ? T[P] extends true
        ? number
        : GetScalarType<T[P], AggregatePProduct[P]>
      : GetScalarType<T[P], AggregatePProduct[P]>
  }




  export type PProductGroupByArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    where?: PProductWhereInput
    orderBy?: PProductOrderByWithAggregationInput | PProductOrderByWithAggregationInput[]
    by: PProductScalarFieldEnum[] | PProductScalarFieldEnum
    having?: PProductScalarWhereWithAggregatesInput
    take?: number
    skip?: number
    _count?: PProductCountAggregateInputType | true
    _avg?: PProductAvgAggregateInputType
    _sum?: PProductSumAggregateInputType
    _min?: PProductMinAggregateInputType
    _max?: PProductMaxAggregateInputType
  }

  export type PProductGroupByOutputType = {
    id: number
    sku: string
    name: string
    price: Decimal
    stock: number
    meta: JsonValue | null
    _count: PProductCountAggregateOutputType | null
    _avg: PProductAvgAggregateOutputType | null
    _sum: PProductSumAggregateOutputType | null
    _min: PProductMinAggregateOutputType | null
    _max: PProductMaxAggregateOutputType | null
  }

  type GetPProductGroupByPayload<T extends PProductGroupByArgs> = Prisma.PrismaPromise<
    Array<
      PickEnumerable<PProductGroupByOutputType, T['by']> &
        {
          [P in ((keyof T) & (keyof PProductGroupByOutputType))]: P extends '_count'
            ? T[P] extends boolean
              ? number
              : GetScalarType<T[P], PProductGroupByOutputType[P]>
            : GetScalarType<T[P], PProductGroupByOutputType[P]>
        }
      >
    >


  export type PProductSelect<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = $Extensions.GetSelect<{
    id?: boolean
    sku?: boolean
    name?: boolean
    price?: boolean
    stock?: boolean
    meta?: boolean
  }, ExtArgs["result"]["pProduct"]>



  export type PProductSelectScalar = {
    id?: boolean
    sku?: boolean
    name?: boolean
    price?: boolean
    stock?: boolean
    meta?: boolean
  }

  export type PProductOmit<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = $Extensions.GetOmit<"id" | "sku" | "name" | "price" | "stock" | "meta", ExtArgs["result"]["pProduct"]>

  export type $PProductPayload<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    name: "PProduct"
    objects: {}
    scalars: $Extensions.GetPayloadResult<{
      id: number
      sku: string
      name: string
      price: Prisma.Decimal
      stock: number
      meta: Prisma.JsonValue | null
    }, ExtArgs["result"]["pProduct"]>
    composites: {}
  }

  type PProductGetPayload<S extends boolean | null | undefined | PProductDefaultArgs> = $Result.GetResult<Prisma.$PProductPayload, S>

  type PProductCountArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> =
    Omit<PProductFindManyArgs, 'select' | 'include' | 'distinct' | 'omit'> & {
      select?: PProductCountAggregateInputType | true
    }

  export interface PProductDelegate<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs, GlobalOmitOptions = {}> {
    [K: symbol]: { types: Prisma.TypeMap<ExtArgs>['model']['PProduct'], meta: { name: 'PProduct' } }
    /**
     * Find zero or one PProduct that matches the filter.
     * @param {PProductFindUniqueArgs} args - Arguments to find a PProduct
     * @example
     * // Get one PProduct
     * const pProduct = await prisma.pProduct.findUnique({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findUnique<T extends PProductFindUniqueArgs>(args: SelectSubset<T, PProductFindUniqueArgs<ExtArgs>>): Prisma__PProductClient<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "findUnique", GlobalOmitOptions> | null, null, ExtArgs, GlobalOmitOptions>

    /**
     * Find one PProduct that matches the filter or throw an error with `error.code='P2025'`
     * if no matches were found.
     * @param {PProductFindUniqueOrThrowArgs} args - Arguments to find a PProduct
     * @example
     * // Get one PProduct
     * const pProduct = await prisma.pProduct.findUniqueOrThrow({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findUniqueOrThrow<T extends PProductFindUniqueOrThrowArgs>(args: SelectSubset<T, PProductFindUniqueOrThrowArgs<ExtArgs>>): Prisma__PProductClient<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "findUniqueOrThrow", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Find the first PProduct that matches the filter.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PProductFindFirstArgs} args - Arguments to find a PProduct
     * @example
     * // Get one PProduct
     * const pProduct = await prisma.pProduct.findFirst({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findFirst<T extends PProductFindFirstArgs>(args?: SelectSubset<T, PProductFindFirstArgs<ExtArgs>>): Prisma__PProductClient<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "findFirst", GlobalOmitOptions> | null, null, ExtArgs, GlobalOmitOptions>

    /**
     * Find the first PProduct that matches the filter or
     * throw `PrismaKnownClientError` with `P2025` code if no matches were found.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PProductFindFirstOrThrowArgs} args - Arguments to find a PProduct
     * @example
     * // Get one PProduct
     * const pProduct = await prisma.pProduct.findFirstOrThrow({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     */
    findFirstOrThrow<T extends PProductFindFirstOrThrowArgs>(args?: SelectSubset<T, PProductFindFirstOrThrowArgs<ExtArgs>>): Prisma__PProductClient<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "findFirstOrThrow", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Find zero or more PProducts that matches the filter.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PProductFindManyArgs} args - Arguments to filter and select certain fields only.
     * @example
     * // Get all PProducts
     * const pProducts = await prisma.pProduct.findMany()
     * 
     * // Get first 10 PProducts
     * const pProducts = await prisma.pProduct.findMany({ take: 10 })
     * 
     * // Only select the `id`
     * const pProductWithIdOnly = await prisma.pProduct.findMany({ select: { id: true } })
     * 
     */
    findMany<T extends PProductFindManyArgs>(args?: SelectSubset<T, PProductFindManyArgs<ExtArgs>>): Prisma.PrismaPromise<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "findMany", GlobalOmitOptions>>

    /**
     * Create a PProduct.
     * @param {PProductCreateArgs} args - Arguments to create a PProduct.
     * @example
     * // Create one PProduct
     * const PProduct = await prisma.pProduct.create({
     *   data: {
     *     // ... data to create a PProduct
     *   }
     * })
     * 
     */
    create<T extends PProductCreateArgs>(args: SelectSubset<T, PProductCreateArgs<ExtArgs>>): Prisma__PProductClient<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "create", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Create many PProducts.
     * @param {PProductCreateManyArgs} args - Arguments to create many PProducts.
     * @example
     * // Create many PProducts
     * const pProduct = await prisma.pProduct.createMany({
     *   data: [
     *     // ... provide data here
     *   ]
     * })
     *     
     */
    createMany<T extends PProductCreateManyArgs>(args?: SelectSubset<T, PProductCreateManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Delete a PProduct.
     * @param {PProductDeleteArgs} args - Arguments to delete one PProduct.
     * @example
     * // Delete one PProduct
     * const PProduct = await prisma.pProduct.delete({
     *   where: {
     *     // ... filter to delete one PProduct
     *   }
     * })
     * 
     */
    delete<T extends PProductDeleteArgs>(args: SelectSubset<T, PProductDeleteArgs<ExtArgs>>): Prisma__PProductClient<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "delete", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Update one PProduct.
     * @param {PProductUpdateArgs} args - Arguments to update one PProduct.
     * @example
     * // Update one PProduct
     * const pProduct = await prisma.pProduct.update({
     *   where: {
     *     // ... provide filter here
     *   },
     *   data: {
     *     // ... provide data here
     *   }
     * })
     * 
     */
    update<T extends PProductUpdateArgs>(args: SelectSubset<T, PProductUpdateArgs<ExtArgs>>): Prisma__PProductClient<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "update", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>

    /**
     * Delete zero or more PProducts.
     * @param {PProductDeleteManyArgs} args - Arguments to filter PProducts to delete.
     * @example
     * // Delete a few PProducts
     * const { count } = await prisma.pProduct.deleteMany({
     *   where: {
     *     // ... provide filter here
     *   }
     * })
     * 
     */
    deleteMany<T extends PProductDeleteManyArgs>(args?: SelectSubset<T, PProductDeleteManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Update zero or more PProducts.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PProductUpdateManyArgs} args - Arguments to update one or more rows.
     * @example
     * // Update many PProducts
     * const pProduct = await prisma.pProduct.updateMany({
     *   where: {
     *     // ... provide filter here
     *   },
     *   data: {
     *     // ... provide data here
     *   }
     * })
     * 
     */
    updateMany<T extends PProductUpdateManyArgs>(args: SelectSubset<T, PProductUpdateManyArgs<ExtArgs>>): Prisma.PrismaPromise<BatchPayload>

    /**
     * Create or update one PProduct.
     * @param {PProductUpsertArgs} args - Arguments to update or create a PProduct.
     * @example
     * // Update or create a PProduct
     * const pProduct = await prisma.pProduct.upsert({
     *   create: {
     *     // ... data to create a PProduct
     *   },
     *   update: {
     *     // ... in case it already exists, update
     *   },
     *   where: {
     *     // ... the filter for the PProduct we want to update
     *   }
     * })
     */
    upsert<T extends PProductUpsertArgs>(args: SelectSubset<T, PProductUpsertArgs<ExtArgs>>): Prisma__PProductClient<$Result.GetResult<Prisma.$PProductPayload<ExtArgs>, T, "upsert", GlobalOmitOptions>, never, ExtArgs, GlobalOmitOptions>


    /**
     * Count the number of PProducts.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PProductCountArgs} args - Arguments to filter PProducts to count.
     * @example
     * // Count the number of PProducts
     * const count = await prisma.pProduct.count({
     *   where: {
     *     // ... the filter for the PProducts we want to count
     *   }
     * })
    **/
    count<T extends PProductCountArgs>(
      args?: Subset<T, PProductCountArgs>,
    ): Prisma.PrismaPromise<
      T extends $Utils.Record<'select', any>
        ? T['select'] extends true
          ? number
          : GetScalarType<T['select'], PProductCountAggregateOutputType>
        : number
    >

    /**
     * Allows you to perform aggregations operations on a PProduct.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PProductAggregateArgs} args - Select which aggregations you would like to apply and on what fields.
     * @example
     * // Ordered by age ascending
     * // Where email contains prisma.io
     * // Limited to the 10 users
     * const aggregations = await prisma.user.aggregate({
     *   _avg: {
     *     age: true,
     *   },
     *   where: {
     *     email: {
     *       contains: "prisma.io",
     *     },
     *   },
     *   orderBy: {
     *     age: "asc",
     *   },
     *   take: 10,
     * })
    **/
    aggregate<T extends PProductAggregateArgs>(args: Subset<T, PProductAggregateArgs>): Prisma.PrismaPromise<GetPProductAggregateType<T>>

    /**
     * Group by PProduct.
     * Note, that providing `undefined` is treated as the value not being there.
     * Read more here: https://pris.ly/d/null-undefined
     * @param {PProductGroupByArgs} args - Group by arguments.
     * @example
     * // Group by city, order by createdAt, get count
     * const result = await prisma.user.groupBy({
     *   by: ['city', 'createdAt'],
     *   orderBy: {
     *     createdAt: true
     *   },
     *   _count: {
     *     _all: true
     *   },
     * })
     * 
    **/
    groupBy<
      T extends PProductGroupByArgs,
      HasSelectOrTake extends Or<
        Extends<'skip', Keys<T>>,
        Extends<'take', Keys<T>>
      >,
      OrderByArg extends True extends HasSelectOrTake
        ? { orderBy: PProductGroupByArgs['orderBy'] }
        : { orderBy?: PProductGroupByArgs['orderBy'] },
      OrderFields extends ExcludeUnderscoreKeys<Keys<MaybeTupleToUnion<T['orderBy']>>>,
      ByFields extends MaybeTupleToUnion<T['by']>,
      ByValid extends Has<ByFields, OrderFields>,
      HavingFields extends GetHavingFields<T['having']>,
      HavingValid extends Has<ByFields, HavingFields>,
      ByEmpty extends T['by'] extends never[] ? True : False,
      InputErrors extends ByEmpty extends True
      ? `Error: "by" must not be empty.`
      : HavingValid extends False
      ? {
          [P in HavingFields]: P extends ByFields
            ? never
            : P extends string
            ? `Error: Field "${P}" used in "having" needs to be provided in "by".`
            : [
                Error,
                'Field ',
                P,
                ` in "having" needs to be provided in "by"`,
              ]
        }[HavingFields]
      : 'take' extends Keys<T>
      ? 'orderBy' extends Keys<T>
        ? ByValid extends True
          ? {}
          : {
              [P in OrderFields]: P extends ByFields
                ? never
                : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
            }[OrderFields]
        : 'Error: If you provide "take", you also need to provide "orderBy"'
      : 'skip' extends Keys<T>
      ? 'orderBy' extends Keys<T>
        ? ByValid extends True
          ? {}
          : {
              [P in OrderFields]: P extends ByFields
                ? never
                : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
            }[OrderFields]
        : 'Error: If you provide "skip", you also need to provide "orderBy"'
      : ByValid extends True
      ? {}
      : {
          [P in OrderFields]: P extends ByFields
            ? never
            : `Error: Field "${P}" in "orderBy" needs to be provided in "by"`
        }[OrderFields]
    >(args: SubsetIntersection<T, PProductGroupByArgs, OrderByArg> & InputErrors): {} extends InputErrors ? GetPProductGroupByPayload<T> : Prisma.PrismaPromise<InputErrors>
  /**
   * Fields of the PProduct model
   */
  readonly fields: PProductFieldRefs;
  }

  /**
   * The delegate class that acts as a "Promise-like" for PProduct.
   * Why is this prefixed with `Prisma__`?
   * Because we want to prevent naming conflicts as mentioned in
   * https://github.com/prisma/prisma-client-js/issues/707
   */
  export interface Prisma__PProductClient<T, Null = never, ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs, GlobalOmitOptions = {}> extends Prisma.PrismaPromise<T> {
    readonly [Symbol.toStringTag]: "PrismaPromise"
    /**
     * Attaches callbacks for the resolution and/or rejection of the Promise.
     * @param onfulfilled The callback to execute when the Promise is resolved.
     * @param onrejected The callback to execute when the Promise is rejected.
     * @returns A Promise for the completion of which ever callback is executed.
     */
    then<TResult1 = T, TResult2 = never>(onfulfilled?: ((value: T) => TResult1 | PromiseLike<TResult1>) | undefined | null, onrejected?: ((reason: any) => TResult2 | PromiseLike<TResult2>) | undefined | null): $Utils.JsPromise<TResult1 | TResult2>
    /**
     * Attaches a callback for only the rejection of the Promise.
     * @param onrejected The callback to execute when the Promise is rejected.
     * @returns A Promise for the completion of the callback.
     */
    catch<TResult = never>(onrejected?: ((reason: any) => TResult | PromiseLike<TResult>) | undefined | null): $Utils.JsPromise<T | TResult>
    /**
     * Attaches a callback that is invoked when the Promise is settled (fulfilled or rejected). The
     * resolved value cannot be modified from the callback.
     * @param onfinally The callback to execute when the Promise is settled (fulfilled or rejected).
     * @returns A Promise for the completion of the callback.
     */
    finally(onfinally?: (() => void) | undefined | null): $Utils.JsPromise<T>
  }




  /**
   * Fields of the PProduct model
   */
  interface PProductFieldRefs {
    readonly id: FieldRef<"PProduct", 'Int'>
    readonly sku: FieldRef<"PProduct", 'String'>
    readonly name: FieldRef<"PProduct", 'String'>
    readonly price: FieldRef<"PProduct", 'Decimal'>
    readonly stock: FieldRef<"PProduct", 'Int'>
    readonly meta: FieldRef<"PProduct", 'Json'>
  }
    

  // Custom InputTypes
  /**
   * PProduct findUnique
   */
  export type PProductFindUniqueArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * Filter, which PProduct to fetch.
     */
    where: PProductWhereUniqueInput
  }

  /**
   * PProduct findUniqueOrThrow
   */
  export type PProductFindUniqueOrThrowArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * Filter, which PProduct to fetch.
     */
    where: PProductWhereUniqueInput
  }

  /**
   * PProduct findFirst
   */
  export type PProductFindFirstArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * Filter, which PProduct to fetch.
     */
    where?: PProductWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PProducts to fetch.
     */
    orderBy?: PProductOrderByWithRelationInput | PProductOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for searching for PProducts.
     */
    cursor?: PProductWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PProducts from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PProducts.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PProducts.
     */
    distinct?: PProductScalarFieldEnum | PProductScalarFieldEnum[]
  }

  /**
   * PProduct findFirstOrThrow
   */
  export type PProductFindFirstOrThrowArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * Filter, which PProduct to fetch.
     */
    where?: PProductWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PProducts to fetch.
     */
    orderBy?: PProductOrderByWithRelationInput | PProductOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for searching for PProducts.
     */
    cursor?: PProductWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PProducts from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PProducts.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PProducts.
     */
    distinct?: PProductScalarFieldEnum | PProductScalarFieldEnum[]
  }

  /**
   * PProduct findMany
   */
  export type PProductFindManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * Filter, which PProducts to fetch.
     */
    where?: PProductWhereInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/sorting Sorting Docs}
     * 
     * Determine the order of PProducts to fetch.
     */
    orderBy?: PProductOrderByWithRelationInput | PProductOrderByWithRelationInput[]
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination#cursor-based-pagination Cursor Docs}
     * 
     * Sets the position for listing PProducts.
     */
    cursor?: PProductWhereUniqueInput
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Take `±n` PProducts from the position of the cursor.
     */
    take?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/pagination Pagination Docs}
     * 
     * Skip the first `n` PProducts.
     */
    skip?: number
    /**
     * {@link https://www.prisma.io/docs/concepts/components/prisma-client/distinct Distinct Docs}
     * 
     * Filter by unique combinations of PProducts.
     */
    distinct?: PProductScalarFieldEnum | PProductScalarFieldEnum[]
  }

  /**
   * PProduct create
   */
  export type PProductCreateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * The data needed to create a PProduct.
     */
    data: XOR<PProductCreateInput, PProductUncheckedCreateInput>
  }

  /**
   * PProduct createMany
   */
  export type PProductCreateManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * The data used to create many PProducts.
     */
    data: PProductCreateManyInput | PProductCreateManyInput[]
    skipDuplicates?: boolean
  }

  /**
   * PProduct update
   */
  export type PProductUpdateArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * The data needed to update a PProduct.
     */
    data: XOR<PProductUpdateInput, PProductUncheckedUpdateInput>
    /**
     * Choose, which PProduct to update.
     */
    where: PProductWhereUniqueInput
  }

  /**
   * PProduct updateMany
   */
  export type PProductUpdateManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * The data used to update PProducts.
     */
    data: XOR<PProductUpdateManyMutationInput, PProductUncheckedUpdateManyInput>
    /**
     * Filter which PProducts to update
     */
    where?: PProductWhereInput
    /**
     * Limit how many PProducts to update.
     */
    limit?: number
  }

  /**
   * PProduct upsert
   */
  export type PProductUpsertArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * The filter to search for the PProduct to update in case it exists.
     */
    where: PProductWhereUniqueInput
    /**
     * In case the PProduct found by the `where` argument doesn't exist, create a new PProduct with this data.
     */
    create: XOR<PProductCreateInput, PProductUncheckedCreateInput>
    /**
     * In case the PProduct was found with the provided `where` argument, update it with this data.
     */
    update: XOR<PProductUpdateInput, PProductUncheckedUpdateInput>
  }

  /**
   * PProduct delete
   */
  export type PProductDeleteArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
    /**
     * Filter which PProduct to delete.
     */
    where: PProductWhereUniqueInput
  }

  /**
   * PProduct deleteMany
   */
  export type PProductDeleteManyArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Filter which PProducts to delete
     */
    where?: PProductWhereInput
    /**
     * Limit how many PProducts to delete.
     */
    limit?: number
  }

  /**
   * PProduct without action
   */
  export type PProductDefaultArgs<ExtArgs extends $Extensions.InternalArgs = $Extensions.DefaultArgs> = {
    /**
     * Select specific fields to fetch from the PProduct
     */
    select?: PProductSelect<ExtArgs> | null
    /**
     * Omit specific fields from the PProduct
     */
    omit?: PProductOmit<ExtArgs> | null
  }


  /**
   * Enums
   */

  export const TransactionIsolationLevel: {
    ReadUncommitted: 'ReadUncommitted',
    ReadCommitted: 'ReadCommitted',
    RepeatableRead: 'RepeatableRead',
    Serializable: 'Serializable'
  };

  export type TransactionIsolationLevel = (typeof TransactionIsolationLevel)[keyof typeof TransactionIsolationLevel]


  export const PUserScalarFieldEnum: {
    id: 'id',
    email: 'email',
    name: 'name',
    age: 'age'
  };

  export type PUserScalarFieldEnum = (typeof PUserScalarFieldEnum)[keyof typeof PUserScalarFieldEnum]


  export const PPostScalarFieldEnum: {
    id: 'id',
    title: 'title',
    body: 'body',
    published: 'published',
    views: 'views',
    authorId: 'authorId'
  };

  export type PPostScalarFieldEnum = (typeof PPostScalarFieldEnum)[keyof typeof PPostScalarFieldEnum]


  export const PProductScalarFieldEnum: {
    id: 'id',
    sku: 'sku',
    name: 'name',
    price: 'price',
    stock: 'stock',
    meta: 'meta'
  };

  export type PProductScalarFieldEnum = (typeof PProductScalarFieldEnum)[keyof typeof PProductScalarFieldEnum]


  export const SortOrder: {
    asc: 'asc',
    desc: 'desc'
  };

  export type SortOrder = (typeof SortOrder)[keyof typeof SortOrder]


  export const NullableJsonNullValueInput: {
    DbNull: typeof DbNull,
    JsonNull: typeof JsonNull
  };

  export type NullableJsonNullValueInput = (typeof NullableJsonNullValueInput)[keyof typeof NullableJsonNullValueInput]


  export const NullsOrder: {
    first: 'first',
    last: 'last'
  };

  export type NullsOrder = (typeof NullsOrder)[keyof typeof NullsOrder]


  export const PUserOrderByRelevanceFieldEnum: {
    email: 'email',
    name: 'name'
  };

  export type PUserOrderByRelevanceFieldEnum = (typeof PUserOrderByRelevanceFieldEnum)[keyof typeof PUserOrderByRelevanceFieldEnum]


  export const PPostOrderByRelevanceFieldEnum: {
    title: 'title',
    body: 'body'
  };

  export type PPostOrderByRelevanceFieldEnum = (typeof PPostOrderByRelevanceFieldEnum)[keyof typeof PPostOrderByRelevanceFieldEnum]


  export const JsonNullValueFilter: {
    DbNull: typeof DbNull,
    JsonNull: typeof JsonNull,
    AnyNull: typeof AnyNull
  };

  export type JsonNullValueFilter = (typeof JsonNullValueFilter)[keyof typeof JsonNullValueFilter]


  export const QueryMode: {
    default: 'default',
    insensitive: 'insensitive'
  };

  export type QueryMode = (typeof QueryMode)[keyof typeof QueryMode]


  export const PProductOrderByRelevanceFieldEnum: {
    sku: 'sku',
    name: 'name'
  };

  export type PProductOrderByRelevanceFieldEnum = (typeof PProductOrderByRelevanceFieldEnum)[keyof typeof PProductOrderByRelevanceFieldEnum]


  /**
   * Field references
   */


  /**
   * Reference to a field of type 'Int'
   */
  export type IntFieldRefInput<$PrismaModel> = FieldRefInputType<$PrismaModel, 'Int'>
    


  /**
   * Reference to a field of type 'String'
   */
  export type StringFieldRefInput<$PrismaModel> = FieldRefInputType<$PrismaModel, 'String'>
    


  /**
   * Reference to a field of type 'Boolean'
   */
  export type BooleanFieldRefInput<$PrismaModel> = FieldRefInputType<$PrismaModel, 'Boolean'>
    


  /**
   * Reference to a field of type 'Decimal'
   */
  export type DecimalFieldRefInput<$PrismaModel> = FieldRefInputType<$PrismaModel, 'Decimal'>
    


  /**
   * Reference to a field of type 'Json'
   */
  export type JsonFieldRefInput<$PrismaModel> = FieldRefInputType<$PrismaModel, 'Json'>
    


  /**
   * Reference to a field of type 'QueryMode'
   */
  export type EnumQueryModeFieldRefInput<$PrismaModel> = FieldRefInputType<$PrismaModel, 'QueryMode'>
    


  /**
   * Reference to a field of type 'Float'
   */
  export type FloatFieldRefInput<$PrismaModel> = FieldRefInputType<$PrismaModel, 'Float'>
    
  /**
   * Deep Input Types
   */


  export type PUserWhereInput = {
    AND?: PUserWhereInput | PUserWhereInput[]
    OR?: PUserWhereInput[]
    NOT?: PUserWhereInput | PUserWhereInput[]
    id?: IntFilter<"PUser"> | number
    email?: StringFilter<"PUser"> | string
    name?: StringNullableFilter<"PUser"> | string | null
    age?: IntNullableFilter<"PUser"> | number | null
    posts?: PPostListRelationFilter
  }

  export type PUserOrderByWithRelationInput = {
    id?: SortOrder
    email?: SortOrder
    name?: SortOrderInput | SortOrder
    age?: SortOrderInput | SortOrder
    posts?: PPostOrderByRelationAggregateInput
    _relevance?: PUserOrderByRelevanceInput
  }

  export type PUserWhereUniqueInput = Prisma.AtLeast<{
    id?: number
    email?: string
    AND?: PUserWhereInput | PUserWhereInput[]
    OR?: PUserWhereInput[]
    NOT?: PUserWhereInput | PUserWhereInput[]
    name?: StringNullableFilter<"PUser"> | string | null
    age?: IntNullableFilter<"PUser"> | number | null
    posts?: PPostListRelationFilter
  }, "id" | "email">

  export type PUserOrderByWithAggregationInput = {
    id?: SortOrder
    email?: SortOrder
    name?: SortOrderInput | SortOrder
    age?: SortOrderInput | SortOrder
    _count?: PUserCountOrderByAggregateInput
    _avg?: PUserAvgOrderByAggregateInput
    _max?: PUserMaxOrderByAggregateInput
    _min?: PUserMinOrderByAggregateInput
    _sum?: PUserSumOrderByAggregateInput
  }

  export type PUserScalarWhereWithAggregatesInput = {
    AND?: PUserScalarWhereWithAggregatesInput | PUserScalarWhereWithAggregatesInput[]
    OR?: PUserScalarWhereWithAggregatesInput[]
    NOT?: PUserScalarWhereWithAggregatesInput | PUserScalarWhereWithAggregatesInput[]
    id?: IntWithAggregatesFilter<"PUser"> | number
    email?: StringWithAggregatesFilter<"PUser"> | string
    name?: StringNullableWithAggregatesFilter<"PUser"> | string | null
    age?: IntNullableWithAggregatesFilter<"PUser"> | number | null
  }

  export type PPostWhereInput = {
    AND?: PPostWhereInput | PPostWhereInput[]
    OR?: PPostWhereInput[]
    NOT?: PPostWhereInput | PPostWhereInput[]
    id?: IntFilter<"PPost"> | number
    title?: StringFilter<"PPost"> | string
    body?: StringNullableFilter<"PPost"> | string | null
    published?: BoolFilter<"PPost"> | boolean
    views?: IntFilter<"PPost"> | number
    authorId?: IntFilter<"PPost"> | number
    author?: XOR<PUserScalarRelationFilter, PUserWhereInput>
  }

  export type PPostOrderByWithRelationInput = {
    id?: SortOrder
    title?: SortOrder
    body?: SortOrderInput | SortOrder
    published?: SortOrder
    views?: SortOrder
    authorId?: SortOrder
    author?: PUserOrderByWithRelationInput
    _relevance?: PPostOrderByRelevanceInput
  }

  export type PPostWhereUniqueInput = Prisma.AtLeast<{
    id?: number
    AND?: PPostWhereInput | PPostWhereInput[]
    OR?: PPostWhereInput[]
    NOT?: PPostWhereInput | PPostWhereInput[]
    title?: StringFilter<"PPost"> | string
    body?: StringNullableFilter<"PPost"> | string | null
    published?: BoolFilter<"PPost"> | boolean
    views?: IntFilter<"PPost"> | number
    authorId?: IntFilter<"PPost"> | number
    author?: XOR<PUserScalarRelationFilter, PUserWhereInput>
  }, "id">

  export type PPostOrderByWithAggregationInput = {
    id?: SortOrder
    title?: SortOrder
    body?: SortOrderInput | SortOrder
    published?: SortOrder
    views?: SortOrder
    authorId?: SortOrder
    _count?: PPostCountOrderByAggregateInput
    _avg?: PPostAvgOrderByAggregateInput
    _max?: PPostMaxOrderByAggregateInput
    _min?: PPostMinOrderByAggregateInput
    _sum?: PPostSumOrderByAggregateInput
  }

  export type PPostScalarWhereWithAggregatesInput = {
    AND?: PPostScalarWhereWithAggregatesInput | PPostScalarWhereWithAggregatesInput[]
    OR?: PPostScalarWhereWithAggregatesInput[]
    NOT?: PPostScalarWhereWithAggregatesInput | PPostScalarWhereWithAggregatesInput[]
    id?: IntWithAggregatesFilter<"PPost"> | number
    title?: StringWithAggregatesFilter<"PPost"> | string
    body?: StringNullableWithAggregatesFilter<"PPost"> | string | null
    published?: BoolWithAggregatesFilter<"PPost"> | boolean
    views?: IntWithAggregatesFilter<"PPost"> | number
    authorId?: IntWithAggregatesFilter<"PPost"> | number
  }

  export type PProductWhereInput = {
    AND?: PProductWhereInput | PProductWhereInput[]
    OR?: PProductWhereInput[]
    NOT?: PProductWhereInput | PProductWhereInput[]
    id?: IntFilter<"PProduct"> | number
    sku?: StringFilter<"PProduct"> | string
    name?: StringFilter<"PProduct"> | string
    price?: DecimalFilter<"PProduct"> | Decimal | DecimalJsLike | number | string
    stock?: IntFilter<"PProduct"> | number
    meta?: JsonNullableFilter<"PProduct">
  }

  export type PProductOrderByWithRelationInput = {
    id?: SortOrder
    sku?: SortOrder
    name?: SortOrder
    price?: SortOrder
    stock?: SortOrder
    meta?: SortOrderInput | SortOrder
    _relevance?: PProductOrderByRelevanceInput
  }

  export type PProductWhereUniqueInput = Prisma.AtLeast<{
    id?: number
    sku?: string
    AND?: PProductWhereInput | PProductWhereInput[]
    OR?: PProductWhereInput[]
    NOT?: PProductWhereInput | PProductWhereInput[]
    name?: StringFilter<"PProduct"> | string
    price?: DecimalFilter<"PProduct"> | Decimal | DecimalJsLike | number | string
    stock?: IntFilter<"PProduct"> | number
    meta?: JsonNullableFilter<"PProduct">
  }, "id" | "sku">

  export type PProductOrderByWithAggregationInput = {
    id?: SortOrder
    sku?: SortOrder
    name?: SortOrder
    price?: SortOrder
    stock?: SortOrder
    meta?: SortOrderInput | SortOrder
    _count?: PProductCountOrderByAggregateInput
    _avg?: PProductAvgOrderByAggregateInput
    _max?: PProductMaxOrderByAggregateInput
    _min?: PProductMinOrderByAggregateInput
    _sum?: PProductSumOrderByAggregateInput
  }

  export type PProductScalarWhereWithAggregatesInput = {
    AND?: PProductScalarWhereWithAggregatesInput | PProductScalarWhereWithAggregatesInput[]
    OR?: PProductScalarWhereWithAggregatesInput[]
    NOT?: PProductScalarWhereWithAggregatesInput | PProductScalarWhereWithAggregatesInput[]
    id?: IntWithAggregatesFilter<"PProduct"> | number
    sku?: StringWithAggregatesFilter<"PProduct"> | string
    name?: StringWithAggregatesFilter<"PProduct"> | string
    price?: DecimalWithAggregatesFilter<"PProduct"> | Decimal | DecimalJsLike | number | string
    stock?: IntWithAggregatesFilter<"PProduct"> | number
    meta?: JsonNullableWithAggregatesFilter<"PProduct">
  }

  export type PUserCreateInput = {
    email: string
    name?: string | null
    age?: number | null
    posts?: PPostCreateNestedManyWithoutAuthorInput
  }

  export type PUserUncheckedCreateInput = {
    id?: number
    email: string
    name?: string | null
    age?: number | null
    posts?: PPostUncheckedCreateNestedManyWithoutAuthorInput
  }

  export type PUserUpdateInput = {
    email?: StringFieldUpdateOperationsInput | string
    name?: NullableStringFieldUpdateOperationsInput | string | null
    age?: NullableIntFieldUpdateOperationsInput | number | null
    posts?: PPostUpdateManyWithoutAuthorNestedInput
  }

  export type PUserUncheckedUpdateInput = {
    id?: IntFieldUpdateOperationsInput | number
    email?: StringFieldUpdateOperationsInput | string
    name?: NullableStringFieldUpdateOperationsInput | string | null
    age?: NullableIntFieldUpdateOperationsInput | number | null
    posts?: PPostUncheckedUpdateManyWithoutAuthorNestedInput
  }

  export type PUserCreateManyInput = {
    id?: number
    email: string
    name?: string | null
    age?: number | null
  }

  export type PUserUpdateManyMutationInput = {
    email?: StringFieldUpdateOperationsInput | string
    name?: NullableStringFieldUpdateOperationsInput | string | null
    age?: NullableIntFieldUpdateOperationsInput | number | null
  }

  export type PUserUncheckedUpdateManyInput = {
    id?: IntFieldUpdateOperationsInput | number
    email?: StringFieldUpdateOperationsInput | string
    name?: NullableStringFieldUpdateOperationsInput | string | null
    age?: NullableIntFieldUpdateOperationsInput | number | null
  }

  export type PPostCreateInput = {
    title: string
    body?: string | null
    published?: boolean
    views?: number
    author: PUserCreateNestedOneWithoutPostsInput
  }

  export type PPostUncheckedCreateInput = {
    id?: number
    title: string
    body?: string | null
    published?: boolean
    views?: number
    authorId: number
  }

  export type PPostUpdateInput = {
    title?: StringFieldUpdateOperationsInput | string
    body?: NullableStringFieldUpdateOperationsInput | string | null
    published?: BoolFieldUpdateOperationsInput | boolean
    views?: IntFieldUpdateOperationsInput | number
    author?: PUserUpdateOneRequiredWithoutPostsNestedInput
  }

  export type PPostUncheckedUpdateInput = {
    id?: IntFieldUpdateOperationsInput | number
    title?: StringFieldUpdateOperationsInput | string
    body?: NullableStringFieldUpdateOperationsInput | string | null
    published?: BoolFieldUpdateOperationsInput | boolean
    views?: IntFieldUpdateOperationsInput | number
    authorId?: IntFieldUpdateOperationsInput | number
  }

  export type PPostCreateManyInput = {
    id?: number
    title: string
    body?: string | null
    published?: boolean
    views?: number
    authorId: number
  }

  export type PPostUpdateManyMutationInput = {
    title?: StringFieldUpdateOperationsInput | string
    body?: NullableStringFieldUpdateOperationsInput | string | null
    published?: BoolFieldUpdateOperationsInput | boolean
    views?: IntFieldUpdateOperationsInput | number
  }

  export type PPostUncheckedUpdateManyInput = {
    id?: IntFieldUpdateOperationsInput | number
    title?: StringFieldUpdateOperationsInput | string
    body?: NullableStringFieldUpdateOperationsInput | string | null
    published?: BoolFieldUpdateOperationsInput | boolean
    views?: IntFieldUpdateOperationsInput | number
    authorId?: IntFieldUpdateOperationsInput | number
  }

  export type PProductCreateInput = {
    sku: string
    name: string
    price: Decimal | DecimalJsLike | number | string
    stock?: number
    meta?: NullableJsonNullValueInput | InputJsonValue
  }

  export type PProductUncheckedCreateInput = {
    id?: number
    sku: string
    name: string
    price: Decimal | DecimalJsLike | number | string
    stock?: number
    meta?: NullableJsonNullValueInput | InputJsonValue
  }

  export type PProductUpdateInput = {
    sku?: StringFieldUpdateOperationsInput | string
    name?: StringFieldUpdateOperationsInput | string
    price?: DecimalFieldUpdateOperationsInput | Decimal | DecimalJsLike | number | string
    stock?: IntFieldUpdateOperationsInput | number
    meta?: NullableJsonNullValueInput | InputJsonValue
  }

  export type PProductUncheckedUpdateInput = {
    id?: IntFieldUpdateOperationsInput | number
    sku?: StringFieldUpdateOperationsInput | string
    name?: StringFieldUpdateOperationsInput | string
    price?: DecimalFieldUpdateOperationsInput | Decimal | DecimalJsLike | number | string
    stock?: IntFieldUpdateOperationsInput | number
    meta?: NullableJsonNullValueInput | InputJsonValue
  }

  export type PProductCreateManyInput = {
    id?: number
    sku: string
    name: string
    price: Decimal | DecimalJsLike | number | string
    stock?: number
    meta?: NullableJsonNullValueInput | InputJsonValue
  }

  export type PProductUpdateManyMutationInput = {
    sku?: StringFieldUpdateOperationsInput | string
    name?: StringFieldUpdateOperationsInput | string
    price?: DecimalFieldUpdateOperationsInput | Decimal | DecimalJsLike | number | string
    stock?: IntFieldUpdateOperationsInput | number
    meta?: NullableJsonNullValueInput | InputJsonValue
  }

  export type PProductUncheckedUpdateManyInput = {
    id?: IntFieldUpdateOperationsInput | number
    sku?: StringFieldUpdateOperationsInput | string
    name?: StringFieldUpdateOperationsInput | string
    price?: DecimalFieldUpdateOperationsInput | Decimal | DecimalJsLike | number | string
    stock?: IntFieldUpdateOperationsInput | number
    meta?: NullableJsonNullValueInput | InputJsonValue
  }

  export type IntFilter<$PrismaModel = never> = {
    equals?: number | IntFieldRefInput<$PrismaModel>
    in?: number[]
    notIn?: number[]
    lt?: number | IntFieldRefInput<$PrismaModel>
    lte?: number | IntFieldRefInput<$PrismaModel>
    gt?: number | IntFieldRefInput<$PrismaModel>
    gte?: number | IntFieldRefInput<$PrismaModel>
    not?: NestedIntFilter<$PrismaModel> | number
  }

  export type StringFilter<$PrismaModel = never> = {
    equals?: string | StringFieldRefInput<$PrismaModel>
    in?: string[]
    notIn?: string[]
    lt?: string | StringFieldRefInput<$PrismaModel>
    lte?: string | StringFieldRefInput<$PrismaModel>
    gt?: string | StringFieldRefInput<$PrismaModel>
    gte?: string | StringFieldRefInput<$PrismaModel>
    contains?: string | StringFieldRefInput<$PrismaModel>
    startsWith?: string | StringFieldRefInput<$PrismaModel>
    endsWith?: string | StringFieldRefInput<$PrismaModel>
    search?: string
    not?: NestedStringFilter<$PrismaModel> | string
  }

  export type StringNullableFilter<$PrismaModel = never> = {
    equals?: string | StringFieldRefInput<$PrismaModel> | null
    in?: string[] | null
    notIn?: string[] | null
    lt?: string | StringFieldRefInput<$PrismaModel>
    lte?: string | StringFieldRefInput<$PrismaModel>
    gt?: string | StringFieldRefInput<$PrismaModel>
    gte?: string | StringFieldRefInput<$PrismaModel>
    contains?: string | StringFieldRefInput<$PrismaModel>
    startsWith?: string | StringFieldRefInput<$PrismaModel>
    endsWith?: string | StringFieldRefInput<$PrismaModel>
    search?: string
    not?: NestedStringNullableFilter<$PrismaModel> | string | null
  }

  export type IntNullableFilter<$PrismaModel = never> = {
    equals?: number | IntFieldRefInput<$PrismaModel> | null
    in?: number[] | null
    notIn?: number[] | null
    lt?: number | IntFieldRefInput<$PrismaModel>
    lte?: number | IntFieldRefInput<$PrismaModel>
    gt?: number | IntFieldRefInput<$PrismaModel>
    gte?: number | IntFieldRefInput<$PrismaModel>
    not?: NestedIntNullableFilter<$PrismaModel> | number | null
  }

  export type PPostListRelationFilter = {
    every?: PPostWhereInput
    some?: PPostWhereInput
    none?: PPostWhereInput
  }

  export type SortOrderInput = {
    sort: SortOrder
    nulls?: NullsOrder
  }

  export type PPostOrderByRelationAggregateInput = {
    _count?: SortOrder
  }

  export type PUserOrderByRelevanceInput = {
    fields: PUserOrderByRelevanceFieldEnum | PUserOrderByRelevanceFieldEnum[]
    sort: SortOrder
    search: string
  }

  export type PUserCountOrderByAggregateInput = {
    id?: SortOrder
    email?: SortOrder
    name?: SortOrder
    age?: SortOrder
  }

  export type PUserAvgOrderByAggregateInput = {
    id?: SortOrder
    age?: SortOrder
  }

  export type PUserMaxOrderByAggregateInput = {
    id?: SortOrder
    email?: SortOrder
    name?: SortOrder
    age?: SortOrder
  }

  export type PUserMinOrderByAggregateInput = {
    id?: SortOrder
    email?: SortOrder
    name?: SortOrder
    age?: SortOrder
  }

  export type PUserSumOrderByAggregateInput = {
    id?: SortOrder
    age?: SortOrder
  }

  export type IntWithAggregatesFilter<$PrismaModel = never> = {
    equals?: number | IntFieldRefInput<$PrismaModel>
    in?: number[]
    notIn?: number[]
    lt?: number | IntFieldRefInput<$PrismaModel>
    lte?: number | IntFieldRefInput<$PrismaModel>
    gt?: number | IntFieldRefInput<$PrismaModel>
    gte?: number | IntFieldRefInput<$PrismaModel>
    not?: NestedIntWithAggregatesFilter<$PrismaModel> | number
    _count?: NestedIntFilter<$PrismaModel>
    _avg?: NestedFloatFilter<$PrismaModel>
    _sum?: NestedIntFilter<$PrismaModel>
    _min?: NestedIntFilter<$PrismaModel>
    _max?: NestedIntFilter<$PrismaModel>
  }

  export type StringWithAggregatesFilter<$PrismaModel = never> = {
    equals?: string | StringFieldRefInput<$PrismaModel>
    in?: string[]
    notIn?: string[]
    lt?: string | StringFieldRefInput<$PrismaModel>
    lte?: string | StringFieldRefInput<$PrismaModel>
    gt?: string | StringFieldRefInput<$PrismaModel>
    gte?: string | StringFieldRefInput<$PrismaModel>
    contains?: string | StringFieldRefInput<$PrismaModel>
    startsWith?: string | StringFieldRefInput<$PrismaModel>
    endsWith?: string | StringFieldRefInput<$PrismaModel>
    search?: string
    not?: NestedStringWithAggregatesFilter<$PrismaModel> | string
    _count?: NestedIntFilter<$PrismaModel>
    _min?: NestedStringFilter<$PrismaModel>
    _max?: NestedStringFilter<$PrismaModel>
  }

  export type StringNullableWithAggregatesFilter<$PrismaModel = never> = {
    equals?: string | StringFieldRefInput<$PrismaModel> | null
    in?: string[] | null
    notIn?: string[] | null
    lt?: string | StringFieldRefInput<$PrismaModel>
    lte?: string | StringFieldRefInput<$PrismaModel>
    gt?: string | StringFieldRefInput<$PrismaModel>
    gte?: string | StringFieldRefInput<$PrismaModel>
    contains?: string | StringFieldRefInput<$PrismaModel>
    startsWith?: string | StringFieldRefInput<$PrismaModel>
    endsWith?: string | StringFieldRefInput<$PrismaModel>
    search?: string
    not?: NestedStringNullableWithAggregatesFilter<$PrismaModel> | string | null
    _count?: NestedIntNullableFilter<$PrismaModel>
    _min?: NestedStringNullableFilter<$PrismaModel>
    _max?: NestedStringNullableFilter<$PrismaModel>
  }

  export type IntNullableWithAggregatesFilter<$PrismaModel = never> = {
    equals?: number | IntFieldRefInput<$PrismaModel> | null
    in?: number[] | null
    notIn?: number[] | null
    lt?: number | IntFieldRefInput<$PrismaModel>
    lte?: number | IntFieldRefInput<$PrismaModel>
    gt?: number | IntFieldRefInput<$PrismaModel>
    gte?: number | IntFieldRefInput<$PrismaModel>
    not?: NestedIntNullableWithAggregatesFilter<$PrismaModel> | number | null
    _count?: NestedIntNullableFilter<$PrismaModel>
    _avg?: NestedFloatNullableFilter<$PrismaModel>
    _sum?: NestedIntNullableFilter<$PrismaModel>
    _min?: NestedIntNullableFilter<$PrismaModel>
    _max?: NestedIntNullableFilter<$PrismaModel>
  }

  export type BoolFilter<$PrismaModel = never> = {
    equals?: boolean | BooleanFieldRefInput<$PrismaModel>
    not?: NestedBoolFilter<$PrismaModel> | boolean
  }

  export type PUserScalarRelationFilter = {
    is?: PUserWhereInput
    isNot?: PUserWhereInput
  }

  export type PPostOrderByRelevanceInput = {
    fields: PPostOrderByRelevanceFieldEnum | PPostOrderByRelevanceFieldEnum[]
    sort: SortOrder
    search: string
  }

  export type PPostCountOrderByAggregateInput = {
    id?: SortOrder
    title?: SortOrder
    body?: SortOrder
    published?: SortOrder
    views?: SortOrder
    authorId?: SortOrder
  }

  export type PPostAvgOrderByAggregateInput = {
    id?: SortOrder
    views?: SortOrder
    authorId?: SortOrder
  }

  export type PPostMaxOrderByAggregateInput = {
    id?: SortOrder
    title?: SortOrder
    body?: SortOrder
    published?: SortOrder
    views?: SortOrder
    authorId?: SortOrder
  }

  export type PPostMinOrderByAggregateInput = {
    id?: SortOrder
    title?: SortOrder
    body?: SortOrder
    published?: SortOrder
    views?: SortOrder
    authorId?: SortOrder
  }

  export type PPostSumOrderByAggregateInput = {
    id?: SortOrder
    views?: SortOrder
    authorId?: SortOrder
  }

  export type BoolWithAggregatesFilter<$PrismaModel = never> = {
    equals?: boolean | BooleanFieldRefInput<$PrismaModel>
    not?: NestedBoolWithAggregatesFilter<$PrismaModel> | boolean
    _count?: NestedIntFilter<$PrismaModel>
    _min?: NestedBoolFilter<$PrismaModel>
    _max?: NestedBoolFilter<$PrismaModel>
  }

  export type DecimalFilter<$PrismaModel = never> = {
    equals?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    in?: Decimal[] | DecimalJsLike[] | number[] | string[]
    notIn?: Decimal[] | DecimalJsLike[] | number[] | string[]
    lt?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    lte?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    gt?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    gte?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    not?: NestedDecimalFilter<$PrismaModel> | Decimal | DecimalJsLike | number | string
  }
  export type JsonNullableFilter<$PrismaModel = never> =
    | PatchUndefined<
        Either<Required<JsonNullableFilterBase<$PrismaModel>>, Exclude<keyof Required<JsonNullableFilterBase<$PrismaModel>>, 'path'>>,
        Required<JsonNullableFilterBase<$PrismaModel>>
      >
    | OptionalFlat<Omit<Required<JsonNullableFilterBase<$PrismaModel>>, 'path'>>

  export type JsonNullableFilterBase<$PrismaModel = never> = {
    equals?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | JsonNullValueFilter
    path?: string
    mode?: QueryMode | EnumQueryModeFieldRefInput<$PrismaModel>
    string_contains?: string | StringFieldRefInput<$PrismaModel>
    string_starts_with?: string | StringFieldRefInput<$PrismaModel>
    string_ends_with?: string | StringFieldRefInput<$PrismaModel>
    array_starts_with?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    array_ends_with?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    array_contains?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    lt?: InputJsonValue
    lte?: InputJsonValue
    gt?: InputJsonValue
    gte?: InputJsonValue
    not?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | JsonNullValueFilter
  }

  export type PProductOrderByRelevanceInput = {
    fields: PProductOrderByRelevanceFieldEnum | PProductOrderByRelevanceFieldEnum[]
    sort: SortOrder
    search: string
  }

  export type PProductCountOrderByAggregateInput = {
    id?: SortOrder
    sku?: SortOrder
    name?: SortOrder
    price?: SortOrder
    stock?: SortOrder
    meta?: SortOrder
  }

  export type PProductAvgOrderByAggregateInput = {
    id?: SortOrder
    price?: SortOrder
    stock?: SortOrder
  }

  export type PProductMaxOrderByAggregateInput = {
    id?: SortOrder
    sku?: SortOrder
    name?: SortOrder
    price?: SortOrder
    stock?: SortOrder
  }

  export type PProductMinOrderByAggregateInput = {
    id?: SortOrder
    sku?: SortOrder
    name?: SortOrder
    price?: SortOrder
    stock?: SortOrder
  }

  export type PProductSumOrderByAggregateInput = {
    id?: SortOrder
    price?: SortOrder
    stock?: SortOrder
  }

  export type DecimalWithAggregatesFilter<$PrismaModel = never> = {
    equals?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    in?: Decimal[] | DecimalJsLike[] | number[] | string[]
    notIn?: Decimal[] | DecimalJsLike[] | number[] | string[]
    lt?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    lte?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    gt?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    gte?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    not?: NestedDecimalWithAggregatesFilter<$PrismaModel> | Decimal | DecimalJsLike | number | string
    _count?: NestedIntFilter<$PrismaModel>
    _avg?: NestedDecimalFilter<$PrismaModel>
    _sum?: NestedDecimalFilter<$PrismaModel>
    _min?: NestedDecimalFilter<$PrismaModel>
    _max?: NestedDecimalFilter<$PrismaModel>
  }
  export type JsonNullableWithAggregatesFilter<$PrismaModel = never> =
    | PatchUndefined<
        Either<Required<JsonNullableWithAggregatesFilterBase<$PrismaModel>>, Exclude<keyof Required<JsonNullableWithAggregatesFilterBase<$PrismaModel>>, 'path'>>,
        Required<JsonNullableWithAggregatesFilterBase<$PrismaModel>>
      >
    | OptionalFlat<Omit<Required<JsonNullableWithAggregatesFilterBase<$PrismaModel>>, 'path'>>

  export type JsonNullableWithAggregatesFilterBase<$PrismaModel = never> = {
    equals?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | JsonNullValueFilter
    path?: string
    mode?: QueryMode | EnumQueryModeFieldRefInput<$PrismaModel>
    string_contains?: string | StringFieldRefInput<$PrismaModel>
    string_starts_with?: string | StringFieldRefInput<$PrismaModel>
    string_ends_with?: string | StringFieldRefInput<$PrismaModel>
    array_starts_with?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    array_ends_with?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    array_contains?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    lt?: InputJsonValue
    lte?: InputJsonValue
    gt?: InputJsonValue
    gte?: InputJsonValue
    not?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | JsonNullValueFilter
    _count?: NestedIntNullableFilter<$PrismaModel>
    _min?: NestedJsonNullableFilter<$PrismaModel>
    _max?: NestedJsonNullableFilter<$PrismaModel>
  }

  export type PPostCreateNestedManyWithoutAuthorInput = {
    create?: XOR<PPostCreateWithoutAuthorInput, PPostUncheckedCreateWithoutAuthorInput> | PPostCreateWithoutAuthorInput[] | PPostUncheckedCreateWithoutAuthorInput[]
    connectOrCreate?: PPostCreateOrConnectWithoutAuthorInput | PPostCreateOrConnectWithoutAuthorInput[]
    createMany?: PPostCreateManyAuthorInputEnvelope
    connect?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
  }

  export type PPostUncheckedCreateNestedManyWithoutAuthorInput = {
    create?: XOR<PPostCreateWithoutAuthorInput, PPostUncheckedCreateWithoutAuthorInput> | PPostCreateWithoutAuthorInput[] | PPostUncheckedCreateWithoutAuthorInput[]
    connectOrCreate?: PPostCreateOrConnectWithoutAuthorInput | PPostCreateOrConnectWithoutAuthorInput[]
    createMany?: PPostCreateManyAuthorInputEnvelope
    connect?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
  }

  export type StringFieldUpdateOperationsInput = {
    set?: string
  }

  export type NullableStringFieldUpdateOperationsInput = {
    set?: string | null
  }

  export type NullableIntFieldUpdateOperationsInput = {
    set?: number | null
    increment?: number
    decrement?: number
    multiply?: number
    divide?: number
  }

  export type PPostUpdateManyWithoutAuthorNestedInput = {
    create?: XOR<PPostCreateWithoutAuthorInput, PPostUncheckedCreateWithoutAuthorInput> | PPostCreateWithoutAuthorInput[] | PPostUncheckedCreateWithoutAuthorInput[]
    connectOrCreate?: PPostCreateOrConnectWithoutAuthorInput | PPostCreateOrConnectWithoutAuthorInput[]
    upsert?: PPostUpsertWithWhereUniqueWithoutAuthorInput | PPostUpsertWithWhereUniqueWithoutAuthorInput[]
    createMany?: PPostCreateManyAuthorInputEnvelope
    set?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
    disconnect?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
    delete?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
    connect?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
    update?: PPostUpdateWithWhereUniqueWithoutAuthorInput | PPostUpdateWithWhereUniqueWithoutAuthorInput[]
    updateMany?: PPostUpdateManyWithWhereWithoutAuthorInput | PPostUpdateManyWithWhereWithoutAuthorInput[]
    deleteMany?: PPostScalarWhereInput | PPostScalarWhereInput[]
  }

  export type IntFieldUpdateOperationsInput = {
    set?: number
    increment?: number
    decrement?: number
    multiply?: number
    divide?: number
  }

  export type PPostUncheckedUpdateManyWithoutAuthorNestedInput = {
    create?: XOR<PPostCreateWithoutAuthorInput, PPostUncheckedCreateWithoutAuthorInput> | PPostCreateWithoutAuthorInput[] | PPostUncheckedCreateWithoutAuthorInput[]
    connectOrCreate?: PPostCreateOrConnectWithoutAuthorInput | PPostCreateOrConnectWithoutAuthorInput[]
    upsert?: PPostUpsertWithWhereUniqueWithoutAuthorInput | PPostUpsertWithWhereUniqueWithoutAuthorInput[]
    createMany?: PPostCreateManyAuthorInputEnvelope
    set?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
    disconnect?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
    delete?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
    connect?: PPostWhereUniqueInput | PPostWhereUniqueInput[]
    update?: PPostUpdateWithWhereUniqueWithoutAuthorInput | PPostUpdateWithWhereUniqueWithoutAuthorInput[]
    updateMany?: PPostUpdateManyWithWhereWithoutAuthorInput | PPostUpdateManyWithWhereWithoutAuthorInput[]
    deleteMany?: PPostScalarWhereInput | PPostScalarWhereInput[]
  }

  export type PUserCreateNestedOneWithoutPostsInput = {
    create?: XOR<PUserCreateWithoutPostsInput, PUserUncheckedCreateWithoutPostsInput>
    connectOrCreate?: PUserCreateOrConnectWithoutPostsInput
    connect?: PUserWhereUniqueInput
  }

  export type BoolFieldUpdateOperationsInput = {
    set?: boolean
  }

  export type PUserUpdateOneRequiredWithoutPostsNestedInput = {
    create?: XOR<PUserCreateWithoutPostsInput, PUserUncheckedCreateWithoutPostsInput>
    connectOrCreate?: PUserCreateOrConnectWithoutPostsInput
    upsert?: PUserUpsertWithoutPostsInput
    connect?: PUserWhereUniqueInput
    update?: XOR<XOR<PUserUpdateToOneWithWhereWithoutPostsInput, PUserUpdateWithoutPostsInput>, PUserUncheckedUpdateWithoutPostsInput>
  }

  export type DecimalFieldUpdateOperationsInput = {
    set?: Decimal | DecimalJsLike | number | string
    increment?: Decimal | DecimalJsLike | number | string
    decrement?: Decimal | DecimalJsLike | number | string
    multiply?: Decimal | DecimalJsLike | number | string
    divide?: Decimal | DecimalJsLike | number | string
  }

  export type NestedIntFilter<$PrismaModel = never> = {
    equals?: number | IntFieldRefInput<$PrismaModel>
    in?: number[]
    notIn?: number[]
    lt?: number | IntFieldRefInput<$PrismaModel>
    lte?: number | IntFieldRefInput<$PrismaModel>
    gt?: number | IntFieldRefInput<$PrismaModel>
    gte?: number | IntFieldRefInput<$PrismaModel>
    not?: NestedIntFilter<$PrismaModel> | number
  }

  export type NestedStringFilter<$PrismaModel = never> = {
    equals?: string | StringFieldRefInput<$PrismaModel>
    in?: string[]
    notIn?: string[]
    lt?: string | StringFieldRefInput<$PrismaModel>
    lte?: string | StringFieldRefInput<$PrismaModel>
    gt?: string | StringFieldRefInput<$PrismaModel>
    gte?: string | StringFieldRefInput<$PrismaModel>
    contains?: string | StringFieldRefInput<$PrismaModel>
    startsWith?: string | StringFieldRefInput<$PrismaModel>
    endsWith?: string | StringFieldRefInput<$PrismaModel>
    search?: string
    not?: NestedStringFilter<$PrismaModel> | string
  }

  export type NestedStringNullableFilter<$PrismaModel = never> = {
    equals?: string | StringFieldRefInput<$PrismaModel> | null
    in?: string[] | null
    notIn?: string[] | null
    lt?: string | StringFieldRefInput<$PrismaModel>
    lte?: string | StringFieldRefInput<$PrismaModel>
    gt?: string | StringFieldRefInput<$PrismaModel>
    gte?: string | StringFieldRefInput<$PrismaModel>
    contains?: string | StringFieldRefInput<$PrismaModel>
    startsWith?: string | StringFieldRefInput<$PrismaModel>
    endsWith?: string | StringFieldRefInput<$PrismaModel>
    search?: string
    not?: NestedStringNullableFilter<$PrismaModel> | string | null
  }

  export type NestedIntNullableFilter<$PrismaModel = never> = {
    equals?: number | IntFieldRefInput<$PrismaModel> | null
    in?: number[] | null
    notIn?: number[] | null
    lt?: number | IntFieldRefInput<$PrismaModel>
    lte?: number | IntFieldRefInput<$PrismaModel>
    gt?: number | IntFieldRefInput<$PrismaModel>
    gte?: number | IntFieldRefInput<$PrismaModel>
    not?: NestedIntNullableFilter<$PrismaModel> | number | null
  }

  export type NestedIntWithAggregatesFilter<$PrismaModel = never> = {
    equals?: number | IntFieldRefInput<$PrismaModel>
    in?: number[]
    notIn?: number[]
    lt?: number | IntFieldRefInput<$PrismaModel>
    lte?: number | IntFieldRefInput<$PrismaModel>
    gt?: number | IntFieldRefInput<$PrismaModel>
    gte?: number | IntFieldRefInput<$PrismaModel>
    not?: NestedIntWithAggregatesFilter<$PrismaModel> | number
    _count?: NestedIntFilter<$PrismaModel>
    _avg?: NestedFloatFilter<$PrismaModel>
    _sum?: NestedIntFilter<$PrismaModel>
    _min?: NestedIntFilter<$PrismaModel>
    _max?: NestedIntFilter<$PrismaModel>
  }

  export type NestedFloatFilter<$PrismaModel = never> = {
    equals?: number | FloatFieldRefInput<$PrismaModel>
    in?: number[]
    notIn?: number[]
    lt?: number | FloatFieldRefInput<$PrismaModel>
    lte?: number | FloatFieldRefInput<$PrismaModel>
    gt?: number | FloatFieldRefInput<$PrismaModel>
    gte?: number | FloatFieldRefInput<$PrismaModel>
    not?: NestedFloatFilter<$PrismaModel> | number
  }

  export type NestedStringWithAggregatesFilter<$PrismaModel = never> = {
    equals?: string | StringFieldRefInput<$PrismaModel>
    in?: string[]
    notIn?: string[]
    lt?: string | StringFieldRefInput<$PrismaModel>
    lte?: string | StringFieldRefInput<$PrismaModel>
    gt?: string | StringFieldRefInput<$PrismaModel>
    gte?: string | StringFieldRefInput<$PrismaModel>
    contains?: string | StringFieldRefInput<$PrismaModel>
    startsWith?: string | StringFieldRefInput<$PrismaModel>
    endsWith?: string | StringFieldRefInput<$PrismaModel>
    search?: string
    not?: NestedStringWithAggregatesFilter<$PrismaModel> | string
    _count?: NestedIntFilter<$PrismaModel>
    _min?: NestedStringFilter<$PrismaModel>
    _max?: NestedStringFilter<$PrismaModel>
  }

  export type NestedStringNullableWithAggregatesFilter<$PrismaModel = never> = {
    equals?: string | StringFieldRefInput<$PrismaModel> | null
    in?: string[] | null
    notIn?: string[] | null
    lt?: string | StringFieldRefInput<$PrismaModel>
    lte?: string | StringFieldRefInput<$PrismaModel>
    gt?: string | StringFieldRefInput<$PrismaModel>
    gte?: string | StringFieldRefInput<$PrismaModel>
    contains?: string | StringFieldRefInput<$PrismaModel>
    startsWith?: string | StringFieldRefInput<$PrismaModel>
    endsWith?: string | StringFieldRefInput<$PrismaModel>
    search?: string
    not?: NestedStringNullableWithAggregatesFilter<$PrismaModel> | string | null
    _count?: NestedIntNullableFilter<$PrismaModel>
    _min?: NestedStringNullableFilter<$PrismaModel>
    _max?: NestedStringNullableFilter<$PrismaModel>
  }

  export type NestedIntNullableWithAggregatesFilter<$PrismaModel = never> = {
    equals?: number | IntFieldRefInput<$PrismaModel> | null
    in?: number[] | null
    notIn?: number[] | null
    lt?: number | IntFieldRefInput<$PrismaModel>
    lte?: number | IntFieldRefInput<$PrismaModel>
    gt?: number | IntFieldRefInput<$PrismaModel>
    gte?: number | IntFieldRefInput<$PrismaModel>
    not?: NestedIntNullableWithAggregatesFilter<$PrismaModel> | number | null
    _count?: NestedIntNullableFilter<$PrismaModel>
    _avg?: NestedFloatNullableFilter<$PrismaModel>
    _sum?: NestedIntNullableFilter<$PrismaModel>
    _min?: NestedIntNullableFilter<$PrismaModel>
    _max?: NestedIntNullableFilter<$PrismaModel>
  }

  export type NestedFloatNullableFilter<$PrismaModel = never> = {
    equals?: number | FloatFieldRefInput<$PrismaModel> | null
    in?: number[] | null
    notIn?: number[] | null
    lt?: number | FloatFieldRefInput<$PrismaModel>
    lte?: number | FloatFieldRefInput<$PrismaModel>
    gt?: number | FloatFieldRefInput<$PrismaModel>
    gte?: number | FloatFieldRefInput<$PrismaModel>
    not?: NestedFloatNullableFilter<$PrismaModel> | number | null
  }

  export type NestedBoolFilter<$PrismaModel = never> = {
    equals?: boolean | BooleanFieldRefInput<$PrismaModel>
    not?: NestedBoolFilter<$PrismaModel> | boolean
  }

  export type NestedBoolWithAggregatesFilter<$PrismaModel = never> = {
    equals?: boolean | BooleanFieldRefInput<$PrismaModel>
    not?: NestedBoolWithAggregatesFilter<$PrismaModel> | boolean
    _count?: NestedIntFilter<$PrismaModel>
    _min?: NestedBoolFilter<$PrismaModel>
    _max?: NestedBoolFilter<$PrismaModel>
  }

  export type NestedDecimalFilter<$PrismaModel = never> = {
    equals?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    in?: Decimal[] | DecimalJsLike[] | number[] | string[]
    notIn?: Decimal[] | DecimalJsLike[] | number[] | string[]
    lt?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    lte?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    gt?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    gte?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    not?: NestedDecimalFilter<$PrismaModel> | Decimal | DecimalJsLike | number | string
  }

  export type NestedDecimalWithAggregatesFilter<$PrismaModel = never> = {
    equals?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    in?: Decimal[] | DecimalJsLike[] | number[] | string[]
    notIn?: Decimal[] | DecimalJsLike[] | number[] | string[]
    lt?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    lte?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    gt?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    gte?: Decimal | DecimalJsLike | number | string | DecimalFieldRefInput<$PrismaModel>
    not?: NestedDecimalWithAggregatesFilter<$PrismaModel> | Decimal | DecimalJsLike | number | string
    _count?: NestedIntFilter<$PrismaModel>
    _avg?: NestedDecimalFilter<$PrismaModel>
    _sum?: NestedDecimalFilter<$PrismaModel>
    _min?: NestedDecimalFilter<$PrismaModel>
    _max?: NestedDecimalFilter<$PrismaModel>
  }
  export type NestedJsonNullableFilter<$PrismaModel = never> =
    | PatchUndefined<
        Either<Required<NestedJsonNullableFilterBase<$PrismaModel>>, Exclude<keyof Required<NestedJsonNullableFilterBase<$PrismaModel>>, 'path'>>,
        Required<NestedJsonNullableFilterBase<$PrismaModel>>
      >
    | OptionalFlat<Omit<Required<NestedJsonNullableFilterBase<$PrismaModel>>, 'path'>>

  export type NestedJsonNullableFilterBase<$PrismaModel = never> = {
    equals?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | JsonNullValueFilter
    path?: string
    mode?: QueryMode | EnumQueryModeFieldRefInput<$PrismaModel>
    string_contains?: string | StringFieldRefInput<$PrismaModel>
    string_starts_with?: string | StringFieldRefInput<$PrismaModel>
    string_ends_with?: string | StringFieldRefInput<$PrismaModel>
    array_starts_with?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    array_ends_with?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    array_contains?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | null
    lt?: InputJsonValue
    lte?: InputJsonValue
    gt?: InputJsonValue
    gte?: InputJsonValue
    not?: InputJsonValue | JsonFieldRefInput<$PrismaModel> | JsonNullValueFilter
  }

  export type PPostCreateWithoutAuthorInput = {
    title: string
    body?: string | null
    published?: boolean
    views?: number
  }

  export type PPostUncheckedCreateWithoutAuthorInput = {
    id?: number
    title: string
    body?: string | null
    published?: boolean
    views?: number
  }

  export type PPostCreateOrConnectWithoutAuthorInput = {
    where: PPostWhereUniqueInput
    create: XOR<PPostCreateWithoutAuthorInput, PPostUncheckedCreateWithoutAuthorInput>
  }

  export type PPostCreateManyAuthorInputEnvelope = {
    data: PPostCreateManyAuthorInput | PPostCreateManyAuthorInput[]
    skipDuplicates?: boolean
  }

  export type PPostUpsertWithWhereUniqueWithoutAuthorInput = {
    where: PPostWhereUniqueInput
    update: XOR<PPostUpdateWithoutAuthorInput, PPostUncheckedUpdateWithoutAuthorInput>
    create: XOR<PPostCreateWithoutAuthorInput, PPostUncheckedCreateWithoutAuthorInput>
  }

  export type PPostUpdateWithWhereUniqueWithoutAuthorInput = {
    where: PPostWhereUniqueInput
    data: XOR<PPostUpdateWithoutAuthorInput, PPostUncheckedUpdateWithoutAuthorInput>
  }

  export type PPostUpdateManyWithWhereWithoutAuthorInput = {
    where: PPostScalarWhereInput
    data: XOR<PPostUpdateManyMutationInput, PPostUncheckedUpdateManyWithoutAuthorInput>
  }

  export type PPostScalarWhereInput = {
    AND?: PPostScalarWhereInput | PPostScalarWhereInput[]
    OR?: PPostScalarWhereInput[]
    NOT?: PPostScalarWhereInput | PPostScalarWhereInput[]
    id?: IntFilter<"PPost"> | number
    title?: StringFilter<"PPost"> | string
    body?: StringNullableFilter<"PPost"> | string | null
    published?: BoolFilter<"PPost"> | boolean
    views?: IntFilter<"PPost"> | number
    authorId?: IntFilter<"PPost"> | number
  }

  export type PUserCreateWithoutPostsInput = {
    email: string
    name?: string | null
    age?: number | null
  }

  export type PUserUncheckedCreateWithoutPostsInput = {
    id?: number
    email: string
    name?: string | null
    age?: number | null
  }

  export type PUserCreateOrConnectWithoutPostsInput = {
    where: PUserWhereUniqueInput
    create: XOR<PUserCreateWithoutPostsInput, PUserUncheckedCreateWithoutPostsInput>
  }

  export type PUserUpsertWithoutPostsInput = {
    update: XOR<PUserUpdateWithoutPostsInput, PUserUncheckedUpdateWithoutPostsInput>
    create: XOR<PUserCreateWithoutPostsInput, PUserUncheckedCreateWithoutPostsInput>
    where?: PUserWhereInput
  }

  export type PUserUpdateToOneWithWhereWithoutPostsInput = {
    where?: PUserWhereInput
    data: XOR<PUserUpdateWithoutPostsInput, PUserUncheckedUpdateWithoutPostsInput>
  }

  export type PUserUpdateWithoutPostsInput = {
    email?: StringFieldUpdateOperationsInput | string
    name?: NullableStringFieldUpdateOperationsInput | string | null
    age?: NullableIntFieldUpdateOperationsInput | number | null
  }

  export type PUserUncheckedUpdateWithoutPostsInput = {
    id?: IntFieldUpdateOperationsInput | number
    email?: StringFieldUpdateOperationsInput | string
    name?: NullableStringFieldUpdateOperationsInput | string | null
    age?: NullableIntFieldUpdateOperationsInput | number | null
  }

  export type PPostCreateManyAuthorInput = {
    id?: number
    title: string
    body?: string | null
    published?: boolean
    views?: number
  }

  export type PPostUpdateWithoutAuthorInput = {
    title?: StringFieldUpdateOperationsInput | string
    body?: NullableStringFieldUpdateOperationsInput | string | null
    published?: BoolFieldUpdateOperationsInput | boolean
    views?: IntFieldUpdateOperationsInput | number
  }

  export type PPostUncheckedUpdateWithoutAuthorInput = {
    id?: IntFieldUpdateOperationsInput | number
    title?: StringFieldUpdateOperationsInput | string
    body?: NullableStringFieldUpdateOperationsInput | string | null
    published?: BoolFieldUpdateOperationsInput | boolean
    views?: IntFieldUpdateOperationsInput | number
  }

  export type PPostUncheckedUpdateManyWithoutAuthorInput = {
    id?: IntFieldUpdateOperationsInput | number
    title?: StringFieldUpdateOperationsInput | string
    body?: NullableStringFieldUpdateOperationsInput | string | null
    published?: BoolFieldUpdateOperationsInput | boolean
    views?: IntFieldUpdateOperationsInput | number
  }



  /**
   * Batch Payload for updateMany & deleteMany & createMany
   */

  export type BatchPayload = {
    count: number
  }

  /**
   * DMMF
   */
  export const dmmf: runtime.BaseDMMF
}