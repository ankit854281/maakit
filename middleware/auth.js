'use strict';
const jwt=require('jsonwebtoken');
const {createPublicKey}=require('node:crypto');
const {HttpError,UUID_V4}=require('../node-api/errors');

// Roles and permissions are loaded from PostgreSQL, never trusted from token claims.
const IDENTITY_SQL=`SELECT u.id,u.session_version,
 ARRAY(SELECT r.code FROM maakit.user_roles ur JOIN maakit.roles r ON r.id=ur.role_id WHERE ur.user_id=u.id) AS roles,
 ARRAY(SELECT DISTINCT p.code FROM maakit.permissions p WHERE p.id IN (
 SELECT rp.permission_id FROM maakit.user_roles ur JOIN maakit.role_permissions rp ON rp.role_id=ur.role_id WHERE ur.user_id=u.id
 UNION SELECT up.permission_id FROM maakit.user_permissions up WHERE up.user_id=u.id)) AS permissions
 FROM maakit.users u JOIN maakit.sessions s ON s.user_id=u.id
 WHERE u.id=$1 AND s.id=$2 AND u.status='ACTIVE' AND s.revoked_at IS NULL
 AND s.expires_at>now() AND u.session_version=$3 AND s.session_version=u.session_version`;

function createAuth({pool,issuer,audience,publicKeys,maxAgeSeconds=900}) {
  if (!pool || typeof pool.query!=='function' || typeof issuer!=='string' || !issuer || typeof audience!=='string' || !audience)
    throw new TypeError('Auth requires a database pool, issuer and audience');
  if (!Number.isSafeInteger(maxAgeSeconds) || maxAgeSeconds<60 || maxAgeSeconds>3600)
    throw new TypeError('Access token age must be between 60 and 3600 seconds');
  if (!publicKeys || typeof publicKeys!=='object' || Array.isArray(publicKeys)) throw new TypeError('Server-owned JWT public key map required');
  const keys=new Map();
  for(const [kid,pem] of Object.entries(publicKeys)) {
    if (!/^[A-Za-z0-9_-]{1,64}$/.test(kid)) throw new TypeError('Invalid key identifier');
    const key=createPublicKey(pem);
    if(key.asymmetricKeyType!=='rsa' || key.asymmetricKeyDetails?.modulusLength<2048) throw new TypeError('RSA public keys must be at least 2048 bits');
    keys.set(kid,key);
  }
  if(!keys.size) throw new TypeError('At least one verification key required');
  return async function authenticate(req,res,next) {
    try {
      const header=req.headers?.authorization;
      if(typeof header!=='string' || header.length>8192 || !/^Bearer [A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/.test(header))
        throw new HttpError(401,'UNAUTHENTICATED','A valid access token is required.');
      const token=header.slice(7);
      let claims;
      try {
        claims=await new Promise((resolve,reject)=>jwt.verify(token,(h,done)=>{
          // No network JWKS fetch, URL/jku/x5u lookup or key selection supplied by a request.
          const key=keys.get(h.kid);
          if(h.alg!=='RS256' || h.typ!=='JWT' || !key || h.jku || h.x5u || h.crit)
            return done(new Error('Disallowed JWT header'));
          done(null,key);
        },{algorithms:['RS256'],issuer,audience,maxAge:`${maxAgeSeconds}s`,clockTolerance:5},(err,value)=>err?reject(err):resolve(value)));
      } catch {throw new HttpError(401,'UNAUTHENTICATED','Access token is invalid or expired.');}
      const now=Math.floor(Date.now()/1000);
      if(!claims || typeof claims!=='object' || typeof claims.sub!=='string' || typeof claims.sid!=='string' || typeof claims.jti!=='string' || !UUID_V4.test(claims.sub || '') || !UUID_V4.test(claims.sid || '') || !UUID_V4.test(claims.jti || '')
        || !Number.isSafeInteger(claims.iat) || !Number.isSafeInteger(claims.exp) || claims.exp<=claims.iat
        || claims.iat>now+5 || claims.exp-claims.iat>maxAgeSeconds || !Number.isSafeInteger(claims.session_version) || claims.session_version<0)
        throw new HttpError(401,'UNAUTHENTICATED','Access token claims are invalid.');
      const {rows}=await pool.query(IDENTITY_SQL,[claims.sub,claims.sid,claims.session_version]);
      if(!rows.length) throw new HttpError(401,'SESSION_REVOKED','Please sign in again.');
      const identity=rows[0];
      req.auth=Object.freeze({userId:identity.id,sessionId:claims.sid,sessionVersion:identity.session_version,
        roles:Object.freeze([...identity.roles]),permissions:Object.freeze([...identity.permissions])});
      next();
    } catch(err) {next(err);}
  };
}
function requireRoles(...roles) {
  if(!roles.length) throw new TypeError('At least one role required');
  return (req,res,next)=>{
    if(!req.auth) return next(new HttpError(401,'UNAUTHENTICATED','Please sign in.'));
    if(!roles.some(r=>req.auth.roles.includes(r))) return next(new HttpError(403,'FORBIDDEN','Your account cannot perform this action.'));
    next();
  };
}
function checkPermission(permission) {
  if(typeof permission!=='string'||!permission) throw new TypeError('Permission required');
  return (req,res,next)=>{
    if(!req.auth) return next(new HttpError(401,'UNAUTHENTICATED','Please sign in.'));
    if(!req.auth.permissions.includes(permission)) return next(new HttpError(403,'FORBIDDEN','Permission required.'));
    next();
  };
}
module.exports={createAuth,requireRoles,checkPermission};
