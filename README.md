jQuery Conditions
=================

**jQuery Conditions** is a new project aiming at providing conditional rule handling for form fields including show/hide/message/validation handling.

# Features
* **Comparison operator**
    * `=`
    * `!=`
    * `>`
    * `>=`
    * `<`
    * `<=`
    * `IN`
    * `NOT IN`

* **Action**
    * Show this field
    * Hide this field
    * Show a message
    * Show an error message (for validation, blocks form submit)

# Roadmap

* **Multiple conditions (see below)**
    * `AND`
    * `OR`
* **What element selector to base logic off of**
* **Comparison operator**
    * `LIKE`
    * `NOT LIKE`
    * `BETWEEN`
    * `NOT BETWEEN`
    * `REGEX MATCH`
    * `REGEX NEGATIVE MATCH`
* **What to compare on**
    * *Value length*
        * Single for `=` `!=` `>` `>=` `<` `<=`
        * Multiple values for `IN` `NOT IN` `BETWEEN` `NOT BETWEEN`
    * *Value to check*
        * Single for `=` `!=` `>` `>=` `<` `<=` `LIKE` `NOT LIKE` `REGEX MATCH` `REGEX NEGATIVE MATCH`
        * Multiple values for `IN` `NOT IN` `BETWEEN` `NOT BETWEEN`
* **PHP functions to output show/hide/validation CSS and messaging**
