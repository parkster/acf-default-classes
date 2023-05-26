import { addDefaultClasses } from './addDefaultClasses.js';

wp.hooks.addFilter(
    'blocks.registerBlockType',
    'acf-default-classes/add-default-classes',
    addDefaultClasses
);