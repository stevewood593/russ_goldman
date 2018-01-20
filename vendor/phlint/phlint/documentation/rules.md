2. Rules
========

Rules define what is a potentially problematic code. Each rule looks for a specific pattern and warns the programmer
if any code matches that pattern. Some rules are such that they, for example, warn about code which causes a fatal
error and everyone agrees that following them is a good practice. However some rules are more subtle and they
are subject to opinions and different coding styles. For this reason it is encouraged that the rules in Phlint should
be as specific as reasonably possible so that programmers have enough flexibility in picking the ones they want to
enforce.

Internally supported rules
--------------------------

- [Prohibit Exit](/documentation/rule/prohibitExit.md)
- [Prohibit Redefining](/documentation/rule/prohibitRedefining.md)
- [Prohibit Unused Imports](/documentation/rule/prohibitUnusedImports.md)
- [Prohibit Variable Append Initialization](/documentation/rule/prohibitVariableAppendInitialization.md)
- [Require Variable Initialization](/documentation/rule/requireVariableInitialization.md)
