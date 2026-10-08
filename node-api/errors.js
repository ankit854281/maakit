'use strict';
const { randomUUID } = require('node:crypto');
class HttpError extends Error {
  constructor(status, code, message) { super(message); this.status=status; this.code=code; }
}
const UUID_V4=/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
function uuid(value, field) {
  if (typeof value!=='string' || !UUID_V4.test(value)) throw new HttpError(400,'INVALID_ID',`${field} must be a UUID v4`);
  return value.toLowerCase();
}
function errorHandler(logger=console) {
  return (err, req, res, next) => {
    if (res.headersSent) return next(err);
    const requestId=req.requestId || randomUUID();
    if(err.type==='entity.parse.failed') err=new HttpError(400,'INVALID_JSON','Provide a valid JSON request.');
    if(err.type==='entity.too.large') err=new HttpError(413,'REQUEST_TOO_LARGE','Request body is too large.');
    // Never log JWTs, SQL text/parameters, addresses, connection URLs or provider secrets.
    if (!(err instanceof HttpError)) logger.error({requestId,code:'API_FAILURE'});
    const status=err instanceof HttpError ? err.status : 503;
    const code=err instanceof HttpError ? err.code : 'SERVICE_UNAVAILABLE';
    res.status(status).json({error:{code,message:err instanceof HttpError?err.message:'Please retry with the same idempotency key.'},request_id:requestId});
  };
}
module.exports={HttpError,uuid,UUID_V4,errorHandler};
