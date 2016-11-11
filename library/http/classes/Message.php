<?php

namespace pillr\library\http;

use \pillr\library\http\Stream as Stream;
use Psr\Http\Message\MessageInterface as MessageInterface;
use Psr\Http\Message\StreamInterface as StreamInterface;
/**
 * HTTP messages consist of requests from a client to a server and responses
 * from a server to a client. This interface defines the methods common to
 * each.
 *
 * Messages are considered immutable{} all methods that might change state MUST
 * be implemented such that they retain the internal state of the current
 * message and return an instance that contains the changed state.
 *
 * @see http://www.ietf.org/rfc/rfc7230.txt
 * @see http://www.ietf.org/rfc/rfc7231.txt
 */
class Message implements MessageInterface
{
    // array containing req/res details
    protected $http;

    // constructor for request class
    protected function Request($protocol, $method, $uri, $headers, $body){
        $this->http = array(
            'protocol' => $protocol,
            'method' => $method,
            'uri' => $uri, //uri object
            'headers' => $headers, //associative array
            'body' => new Stream($body) // stream object
            );
        
        // check validity of passed arguments
        $this->checkArguments($this->http);
    }

    // constructor for response class
    protected function Response($protocol, $status_code, $reason, $headers, $body){
        $this->http = array(
            'protocol' => $protocol,
            'status_code' => $status_code,
            'reason' => $reason,
            'headers' => $headers, //associative array
            'body' => new Stream($body) //stream object
            );
        
        // check validity of passed arguments
        $this->checkArguments($this->http);
    }
    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
     return $this->http['protocol'];
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     * @return self
     */
    public function withProtocolVersion($version)
    {
        $this->isValid($version,'protocol');

        $that = clone $this;
        $that->$http['protocol'] = $version;
        return $that;
    }

    /**
     * Retrieves all message header values.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values){}
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false){}
     *         }
     *     }
     *
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return string[][] Returns an associative array of the message's headers.
     *     Each key MUST be a header name, and each value MUST be an array of
     *     strings for that header.
     */
    public function getHeaders()
    {
        return $this->http['headers'];
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($name)
    {
        $arr = $this->getHeader($name);
        return (count($arr) != 0)? true : false;
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function getHeader($name)
    {
        $arr = array();
        $key = $this->getHeaderKey($name);

        if($key){
            // push all strings at the header key to array
            foreach ($this->http['headers'][$key] as $value){
                array_push($arr, $value);
            }
        }

        return $arr;
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name)
    {
        $headers = $this->getHeader($name);

        return implode(',', $headers);
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return self
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($name, $value)
    {
        $this->isValid($name,'header_name');

        $that = clone $this;
        // $val of a header key must be an array
        $val = (gettype($value) == 'array') ? $value : array($value);
        $key = $that->getHeaderKey($name);

        if($key){
            // key exists, so replace value
            $that->http['headers'][$key] = $val;
        }
        else{
            // create key if it did not exist
            $that->http['headers'][$name] = $val;
        }

        return $that;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $name Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return self
     * @throws \InvalidArgumentException for invalid header names.
     * @throws \InvalidArgumentException for invalid header values.
     */
    public function withAddedHeader($name, $value)
    {
        $this->isValid($name,'header_name');

        $that = clone $this;
        $val = (gettype($value) == 'array') ? $value : array($value);
        $key = $that->getHeaderKey($name);

        if($key){
            // key exists, push to array
            array_push($that->http['headers'][$key], $val);
        }
        else{
            // create key if it did not exist, and save value in array
            $that->http['headers'][$name] = array($val);
        }

        return $that;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name Case-insensitive header field name to remove.
     * @return self
     */
    public function withoutHeader($name)
    {
        $that = clone $this;
        $key = $that->getHeaderKey($name);

        if($key){
            unset($that->http['headers'][$key]);
        }

        return $that;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function getBody()
    {
        return $this->http['body'];
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body.
     * @return self
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body)
    {
        $this->isValid($body,'body');

        $that = clone $this;
        $that->$http['body'] = $body;
        return $that;
    }

    /**
    * Returns key of the queried header with the case originally stored in http array
    * or null if the key is not found.
    *
    * @param string $header
    *
    * @return modified clone of $this
    */
    private function getHeaderKey($header){
        $h = strtolower($header);

        foreach ($this->http['headers'] as $key => $value) {
            if(strtolower($key) == $h){
                return $key;
            }
        }

        return null;
    }

    // VALIDATION
    // checks constructor arguments
    protected function checkArguments($arguments){
        foreach ($arguments as $key => $val) {
            // headers is an exception since it is an array
            if($key == 'headers'){
                $this->checkHeaderNames($val);
            }
            else{
                $this->isValid($val,$key);
            }
        }
    }
    // checks if header names (keys) are valid
    private function checkHeaderNames($headers){
        foreach ($headers as $key => $value) {
            $this->isValid($key,'header_name');
        }
    }

    // validation logic
    protected function isValid($val, $field){
        switch ($field) {
            case 'protocol':
                $protocol = floatval($val);
                if($protocol < 1 || $protocol > 2) {
                    throw new \InvalidArgumentException("protocol version ${val} is invalid");
                }
                break;
            case 'method':
                $method = strtoupper($val);
                if(!in_array($method, $this->methods)){
                    throw new \InvalidArgumentException("http method ${val} is invalid");
                }
                break;
            case 'status_code':
                $code = intval($val);
                if($code < 100 || $code > 599){
                    throw new \InvalidArgumentException("status code ${val} is invalid");
                }
                break;
            case 'header_name':
                $header = strtolower($val);
                $headersArray = array_map('strtolower', $this->headers);
                // case insensitive look-up
                if(!in_array($header, $headersArray)){
                    throw new \InvalidArgumentException("header name ${val} is invalid");
                }
                break;
            case 'body':
                if(!is_a($val, '\pillr\library\http\Stream')){
                    throw new \InvalidArgumentException("body is not of type Stream");
                }
                break;
            
            default:
                break;
        }
    }

    // RESOURCES
    private $methods = array(
    'GET','HEAD','POST',
    'PUT','DELETE','CONNECT',
    'OPTIONS','TRACE');

    private $headers = array(
        'Accept','Accept-Charset','Accept-Encoding',
        'Accept-Language','Accept-Ranges','Access-Control-Allow-Credentials',
        'Access-Control-Allow-Headers','Access-Control-Allow-Methods','Access-Control-Allow-Origin',
        'Access-Control-Expose-Headers','Access-Control-Max-Age','Access-Control-Request-Headers',
        'Access-Control-Request-Method','Age','Cache-Control',
        'Connection','Content-Disposition','Content-Encoding',
        'Content-Language','Content-Length','Content-Location',
        'Content-Security-Policy','Content-Type','Cookie',
        'Cookie2','DNT','Date',
        'ETag','Expires','From',
        'Host','If-Match','If-Modified-Since',
        'If-None-Match','If-Range','If-Unmodified-Since',
        'Keep-Alive','Last-Modified','Location',
        'Origin','Pragma','Referer',
        'Referrer-Policy','Retry-After','Server',
        'Set-Cookie','Set-Cookie2','Strict-Transport-Security',
        'TE','Tk','Trailer',
        'Transfer-Encoding','User-Agent','Vary',
        'Via','Warning','X-Content-Type-Options',
        'X-DNS-Prefetch-Control','X-Frame-Options'
        );
}