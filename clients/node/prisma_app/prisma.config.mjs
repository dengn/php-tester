import { defineConfig } from 'prisma/config';
import { PrismaMariaDb } from '@prisma/adapter-mariadb';

const host = process.env.MO_HOST || '127.0.0.1';
const port = parseInt(process.env.MO_PORT || '6001', 10);
const user = process.env.MO_USER || 'root';
const password = process.env.MO_PASS !== undefined ? process.env.MO_PASS : '111';
const database = 'mo_node_prisma';

export default defineConfig({
  schema: './schema.prisma',
  datasource: { url: `mysql://${user}:${password}@${host}:${port}/${database}` },
  migrations: {
    async adapter() {
      return new PrismaMariaDb({ host, port, user, password, database });
    },
  },
});
