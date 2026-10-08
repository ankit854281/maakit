'use strict';
const {Pool}=require('pg');
function createPool(env=process.env,logger=console) {
  if(!env.MAAKIT_DATABASE_URL) throw new TypeError('MAAKIT_DATABASE_URL is required');
  const url=new URL(env.MAAKIT_DATABASE_URL);
  if(!['postgres:','postgresql:'].includes(url.protocol)) throw new TypeError('PostgreSQL connection required');
  // Prevent connection-string sslmode/options from silently overriding certificate validation.
  for(const p of ['sslmode','sslcert','sslkey','sslrootcert']) if(url.searchParams.has(p)) throw new TypeError('Configure TLS through MAAKIT_PG_CA, not URL SSL options');
  const local=['localhost','127.0.0.1','[::1]'].includes(url.hostname);
  const ssl=local&&env.MAAKIT_PG_LOCAL_PLAINTEXT==='true'?false:{rejectUnauthorized:true,...(env.MAAKIT_PG_CA?{ca:env.MAAKIT_PG_CA}:{})};
  const pool=new Pool({connectionString:url.toString(),ssl,max:10,idleTimeoutMillis:30000,connectionTimeoutMillis:5000});
  pool.on('error',()=>logger.error({code:'DATABASE_POOL_ERROR'}));
  return pool;
}
module.exports={createPool};
