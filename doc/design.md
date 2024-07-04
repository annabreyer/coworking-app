# Design

I am a backend developer, and I am not a designer. I have tried to make the design as clean as possible, but I am sure it can be improved.
Same goes for tools. I do not properly know how to leverage most of the existing ones, and time was short to learn them.

## CSS Framework
I have chosen Tailwind because I know it a little bit already and I like the Flowbite components. Also, it integrates well with symfony. 

### Remaining todos
- replace the colors by aliases like primary color, secondary color etc. so it can be changed more easily.
- remove all dark: classes, they clutter the code, as a dark theme is not planned

## Javascript
Currently, JS is in the templates, it should at one point be moved to controllers and leverage the stimulus framework.
Turbo is disabled, the user menu is not working properly with it. 
