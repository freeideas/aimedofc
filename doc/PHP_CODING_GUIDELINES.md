# PHP Code Guidelines

**Note: These guidelines apply to all programming languages. While examples are shown in specific languages, the core philosophies should be followed regardless of the language being used.**

## Core Philosophy

### Brevity Above All

Fewer lines of code to achieve the same result beats almost every other concern:

- **Clarity**: A few lines of code is clearer than a lot of code
- **Performance**: A few lines of code will often run faster than a lot of code
- **Optimization**: When brevity doesn't improve performance directly, it's easier to optimize a few lines than a large body of code


### Only Write What's Needed

Don't write any code unless there is reason to believe it will be needed. This includes utility methods, helper functions, getters/setters, or any "just in case" code.

### Prefer Early Returns and Breaks

Always make code shorter and flatter when possible instead of nesting blocks:

```php
// AVOID: Deeply nested code
function processItem($item) {
    if ($item !== null) {
        if ($item->isValid()) {
            if ($item->canProcess()) {
                // Process the item
                return true;
            }
        }
    }
    return false;
}

// BETTER: Flat code with early returns
function processItem($item) {
    if ($item === null) {
        return false;
    }
    if (!$item->isValid()) {
        return false;
    }
    if (!$item->canProcess()) {
        return false;
    }
    
    // Process the item
    return true;
}
```

Similarly for loops:

```php
// AVOID: Nested conditions inside loops
while (true) {
    if ($someCondition) {
        doSomeThings();
        doMoreThings();
    } else {
        break;
    }
}

// BETTER: Early break to flatten structure
while (true) {
    if (!$someCondition) break;
    doSomeThings();
    doMoreThings();
}
```

### On Exception/Error Handling

- Do not catch exceptions just to re-throw them
- Let errors bubble up naturally
- Do not clutter code with unnecessary try-catch blocks
- Only catch exceptions when:
  - They need to be part of a return value
  - You're actually handling the error in a meaningful way
  - The language/framework requires explicit error handling

### On Warnings

- Fix all PHP notices, warnings, and strict standards messages
- Warnings can be distracting from real problems
- Clean code runs without warnings
- If a warning can't be fixed, explicitly suppress it with @ operator or error_reporting() and document why

### On Comments

- Comments are almost always lies
  - If they aren't lies right now, they will be after changes happen
  - Writing comments tempts developers to write code that is difficult to understand
  - If code isn't self-explanatory, improve the code rather than explaining it with comments
  - Better code usually means fewer lines of code

#### When Comments Are Acceptable:
- **Surprises**: Document genuinely surprising behavior that someone reading the code might not expect
- **Class/Module Purpose**: A brief comment at the top of a class or file explaining its purpose and what makes it valuable
- **Non-obvious Business Logic**: When the code correctly implements counter-intuitive requirements

Example of a good comment:
```php
/**
 * This class implements a lightweight tree structure that can represent
 * any part of any tree-like data as a node.
 */
```

### On File Management

- Keep the project directory pristine - a clean workspace leads to clearer thinking and easier navigation
- All temporary files must be created in the ./tmp directory within the project root
- Create the ./tmp directory if it doesn't exist: `if (!is_dir('./tmp')) mkdir('./tmp', 0777, true);`
- Never create test files, scratch files, or temporary outputs elsewhere in the project directory
- The only files in the project should be essential source code, configuration, and documentation that serves the project's purpose
- Add ./tmp to .gitignore to prevent temporary files from being committed
- Clean up temporary files when done

### On Testing Philosophy

- Tests should focus on verifying that components behave correctly with valid inputs
- Testing error handling and edge cases is usually not valuable - focus on the happy path
- A component that works correctly with proper inputs is far more important than one that gracefully handles invalid inputs
- Time spent testing error conditions is better spent making the component work better with correct inputs
- Exception: Test error conditions only when they represent important business logic or security boundaries

## Spacing

### Blank Lines
- Use 3 blank lines:
  - Between methods or function definitions
  - Before the first method and after the last method in a class
  - Between namespace declaration and use statements
  - Between use statements and class declaration
  - EXCEPTION: anonymous functions have no blank lines
  - EXCEPTION: trait methods and private methods have one blank line between them

- Use 0 blank lines:
  - Between closely related methods (same name with different args, getter/setter pairs, constructors)
  - Between a method and its test method
  - Within method bodies
  - Within trait definitions

## Code Structure

### Single Statement Blocks
- For single statement blocks, put statement on same line as control structure
- Don't use braces for single statement blocks (except try/catch/finally)
```php
if ($condition) return;
while ($condition) callMethod();
for ($i = 0; $i < 10; $i++) callMethod();
foreach ($items as $item) processItem($item);
```

### Methods
- Methods with single-line bodies should be on same line as signature:
```php
public function methodName() { return $this->callMethod(); }
private function getValue() { return $this->value; }
```

### try/catch/finally blocks
- try/catch/finally with single statement bodies should be on same line as try/catch/finally keyword:
```php
try { callMethod(); }
catch (Exception $e) { error_log($e->getMessage()); }
finally { cleanup(); }
```

## PHP-Specific Guidelines

### PHP Tags
- Always use `<?php` tag, never short tags `<?` or `<?=`
- Omit closing `?>` tag in PHP-only files to prevent accidental whitespace output

### Type Declarations
- Use strict types declaration at the top of every file: `declare(strict_types=1);`
- Use return type declarations and parameter type hints wherever possible:
```php
function processData(array $data, int $limit): bool {
    // Process data
    return true;
}
```

### Null Coalescing
- Use null coalescing operator `??` instead of isset() checks:
```php
// AVOID
$value = isset($_GET['param']) ? $_GET['param'] : 'default';

// BETTER
$value = $_GET['param'] ?? 'default';
```

### Array Syntax
- Use short array syntax `[]` instead of `array()`:
```php
// AVOID
$items = array('one', 'two', 'three');

// BETTER
$items = ['one', 'two', 'three'];
```

### String Interpolation
- Use double quotes for strings with variables instead of concatenation:
```php
// AVOID
$message = 'Hello ' . $name . ', your ID is ' . $id;

// BETTER
$message = "Hello $name, your ID is $id";

// For complex expressions use curly braces
$message = "User {$user->getName()} logged in";
```

## Line Length
- Maximum line length is 120 characters; break lines when they would exceed this limit

## Comments
- Remove EVERY comment unless it explains:
  1. Why this code is needed (it should not exist unless it is needed)
  2. Surprising behavior
  3. Something that won't be obvious to someone who has already read the code

### Examples of Acceptable Comments:
```php
// NOTE: We multiply by 1.5 here because the API returns values in a different unit
$adjustedValue = $apiValue * 1.5;

// NOTE: This sleep is required because the external service has a rate limit
usleep(100000);

class UserCache {
    // NOTE: This cache is needed because this data is fetched frequently and is expensive to retrieve
}
```