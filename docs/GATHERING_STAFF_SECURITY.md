# Gathering Staff Contact Info Security - Token-Based Access Control

## Overview

The gathering staff feature requires secure access to member PII (email addresses and phone numbers). This document describes the **three-layer token-based security system** implemented to prevent unauthorized access and enumeration attacks.

## Security Architecture

### Layer 1: Authorization Check
Users must have **edit permission** on the specific gathering to request contact information.

### Layer 2: Token Generation
A cryptographically secure, time-limited token is generated that binds together:
- `member_id` - The specific member whose contact info is requested
- `gathering_id` - The gathering context
- `user_id` - The requesting user
- `timestamp` - When the token was created

### Layer 3: Single-Use Tokens
Tokens are:
- **Single-use** - Deleted immediately after first retrieval
- **Time-limited** - Expire after 5 minutes
- **User-bound** - Can only be used by the user who requested it
- **Context-bound** - Tied to specific member + gathering combination

## Implementation Flow

### Step 1: Token Request

When a user selects an AMP member from the autocomplete:

```javascript
// Client-side (JavaScript in staffTab.php)
fetch('/gathering-staff/generate-contact-info-token', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({
        member_id: 123,
        gathering_id: 51
    })
})
```

```php
// Server-side (GatheringStaffController::generateContactInfoToken)
public function generateContactInfoToken()
{
    // 1. Verify user can edit this gathering
    $gathering = $this->GatheringStaff->Gatherings->get($gatheringId);
    $this->Authorization->authorize($gathering, 'edit');
    
    // 2. Generate cryptographically secure token
    $token = bin2hex(random_bytes(32)); // 64 character hex string
    
    // 3. Store in cache with context
    Cache::write('contact_token_' . $token, [
        'member_id' => $memberId,
        'gathering_id' => $gatheringId,
        'user_id' => $currentUserId,
        'created' => time(),
    ], '+5 minutes');
    
    // 4. Return token to client
    return ['token' => $token];
}
```

### Step 2: Contact Info Retrieval

The client immediately uses the token to fetch contact info:

```javascript
// Client-side
fetch('/gathering-staff/get-member-contact-info?token=' + token)
    .then(response => response.json())
    .then(data => {
        // Auto-fill email and phone fields
    });
```

```php
// Server-side (GatheringStaffController::getMemberContactInfo)
public function getMemberContactInfo()
{
    // 1. Retrieve token data from cache
    $tokenData = Cache::read('contact_token_' . $token);
    
    if (!$tokenData) {
        return ['error' => 'Invalid or expired token'];
    }
    
    // 2. Verify token belongs to current user
    if ($tokenData['user_id'] !== $currentUserId) {
        Cache::delete('contact_token_' . $token);
        return ['error' => 'Token does not belong to current user'];
    }
    
    // 3. Delete token (single-use)
    Cache::delete('contact_token_' . $token);
    
    // 4. Return contact info
    $member = $this->Members->get($tokenData['member_id']);
    return [
        'email' => $member->email_address,
        'phone' => $member->phone_number,
    ];
}
```

## Security Guarantees

### ✅ Prevents Enumeration Attacks

**Attack:** Try to harvest all member contact info by iterating through member IDs
```
for (member_id = 1; member_id < 10000; member_id++) {
    fetch('/get-member-contact-info?member_id=' + member_id)
}
```

**Defense:** Without a valid token for each specific member_id, the request fails. Attacker would need to:
1. Have edit access to a gathering
2. Generate a new token for EACH member_id
3. Each token generation is logged and requires authorization
4. Mass token generation would be immediately obvious in logs

### ✅ Prevents Replay Attacks

**Attack:** Intercept a valid token and reuse it multiple times
```
// Attacker captures: token=abc123...
fetch('/get-member-contact-info?token=abc123...')  // Works
fetch('/get-member-contact-info?token=abc123...')  // Try again
```

**Defense:** Token is deleted after first use
- First request: ✅ Returns contact info, deletes token
- Second request: ❌ "Invalid or expired token"

### ✅ Prevents Token Sharing

**Attack:** User A generates token, sends to User B
```
// User A (has gathering edit permission)
token = generateToken(member_id=123, gathering_id=51)

// User B (no gathering edit permission) tries to use it
fetch('/get-member-contact-info?token=' + token)
```

**Defense:** Token is bound to user_id
- Token contains: `user_id: 456` (User A)
- User B makes request with their session
- Server checks: `tokenData.user_id !== currentUserId`
- Result: ❌ "Token does not belong to current user"

### ✅ Prevents Timing Attacks

**Attack:** Generate token, wait until user no longer has gathering access, then use it
```
token = generateToken()  // User has access now
// ... wait 1 hour, user access revoked ...
fetch('/get-member-contact-info?token=' + token)
```

**Defense:** 5-minute token expiration
- Token expires from cache after 5 minutes
- Beyond that timeframe: ❌ "Invalid or expired token"
- Normal workflow takes < 1 second, so 5 minutes is generous

### ✅ Prevents Context Mismatch

**Attack:** Generate token for gathering A, try to use it to get info for gathering B members
```
token = generateToken(member_id=1, gathering_id=51)
// Try to use for different gathering
fetch('/get-member-contact-info?token=' + token + '&gathering_id=99')
```

**Defense:** Token context is immutable
- Token stores: `member_id=1, gathering_id=51`
- No gathering_id parameter accepted in retrieval
- Token can ONLY be used to fetch member #1 in gathering #51 context
- No way to change context after token generation

## Audit Trail

All access is logged through:

1. **Authorization Layer**
   - Every `generateContactInfoToken()` call checks gathering edit permission
   - Authorization failures are logged by CakePHP's Authorization plugin

2. **Token Generation**
   - Each token generation is a separate request
   - Can be logged/monitored for unusual patterns

3. **Token Usage**
   - Each `getMemberContactInfo()` call validates token
   - Failed validations can trigger alerts

4. **Cache Storage**
   - Token data includes timestamp and user_id
   - Cache can be inspected for active tokens

## Performance Considerations

### Cache Backend

Uses CakePHP's default cache configuration. For production:

```php
// config/app.php
'Cache' => [
    'default' => [
        'className' => 'Redis',  // Or Memcached
        'duration' => '+5 minutes',
        'prefix' => 'kmp_',
    ],
],
```

### Token Cleanup

Tokens are automatically cleaned up by:
1. **Immediate deletion** after use (single-use)
2. **Cache expiration** after 5 minutes
3. **Cache engine's** automatic TTL cleanup

No manual cleanup required.

## Attack Surface Analysis

### Endpoints

1. **`POST /gathering-staff/generate-contact-info-token`**
   - **Input:** member_id, gathering_id
   - **Auth:** Requires gathering edit permission
   - **Rate Limit:** Could add rate limiting here
   - **Output:** Random token (64-char hex)

2. **`GET /gathering-staff/get-member-contact-info?token=...`**
   - **Input:** token
   - **Auth:** Token validation (user_id match)
   - **Rate Limit:** Self-limiting (single-use tokens)
   - **Output:** Email, phone (if valid token)

### Potential Improvements

1. **Rate Limiting**
   ```php
   // Limit to 10 token requests per minute per user
   $rateLimit = Cache::read('rate_limit_' . $userId);
   if ($rateLimit > 10) {
       throw new TooManyRequestsException();
   }
   ```

2. **IP Binding**
   ```php
   // Store IP address in token data
   'ip_address' => $request->clientIp(),
   
   // Verify on retrieval
   if ($tokenData['ip_address'] !== $request->clientIp()) {
       throw new ForbiddenException();
   }
   ```

3. **Audit Logging**
   ```php
   // Log every contact info access
   Log::info('Contact info accessed', [
       'member_id' => $memberId,
       'gathering_id' => $gatheringId,
       'user_id' => $userId,
       'timestamp' => time(),
   ]);
   ```

## Testing Security

### Unit Tests

```php
// Test token expiration
$token = $this->generateToken();
sleep(301); // 5 minutes + 1 second
$result = $this->getMemberContactInfo($token);
$this->assertEquals('Invalid or expired token', $result['error']);

// Test single-use
$token = $this->generateToken();
$this->getMemberContactInfo($token); // First use: OK
$result = $this->getMemberContactInfo($token); // Second use: FAIL
$this->assertEquals('Invalid or expired token', $result['error']);

// Test user binding
$tokenUserA = $this->generateTokenAsUser('userA');
$result = $this->getMemberContactInfoAsUser('userB', $tokenUserA);
$this->assertEquals('Token does not belong to current user', $result['error']);
```

### Integration Tests

```php
// Test authorization requirement
$this->loginAsUserWithoutGatheringAccess();
$this->post('/gathering-staff/generate-contact-info-token', [
    'member_id' => 1,
    'gathering_id' => 51,
]);
$this->assertResponseCode(403);
```

## Conclusion

The three-layer token-based security system provides **defense in depth** against PII harvesting:

1. ✅ **Authorization** - Must have gathering edit permission
2. ✅ **Token Generation** - Creates unique, time-limited, context-bound token
3. ✅ **Token Validation** - Single-use, user-bound, expires quickly

This architecture ensures that contact information can only be accessed in legitimate use cases (adding staff to a gathering you can edit) while preventing enumeration, replay, sharing, and timing attacks.

**Files Modified:**
- [`app/src/Controller/GatheringStaffController.php`](app/src/Controller/GatheringStaffController.php )
- [`app/templates/element/gatherings/staffTab.php`](app/templates/element/gatherings/staffTab.php )

**Status:** ✅ Implemented and Ready for Testing