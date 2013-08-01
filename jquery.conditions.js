/**
 * jQuery.conditions plugin
**/

// Usage
/*
$('#input').conditions({
   messages_class       : 'msg',             // info messages classname
   error_messages_class : 'err-msg',         // error messages classname
   conditions           : [                  // conditions
      // all groups will be OR-ed
      {
         rules    : [
            // all rules will be AND-ed
            ['value', '=', ['test', 'lorem']],  // all possible values will be OR-ed
            ['length', '>', [1]]
         ],
         actions  : [
            {
               name     : 'error',           // 'show' or 'hide' or 'info' or 'error'
               options  : {
                  message  : 'This field must not be emtpy',
                  wrapper  : null,           // defaults to <input> element
                  position : 'after'         // 'before' or 'after' or 'top' or 'bottom'
               }
            },
            {
               name     : 'show',
               options  : {
                  elements : $('input.optional')
               }
            }
         ]
      }
   ]
});
*/

( function ( $ ) {
   
   /**
    * Manager - our single object that keeps all validators, performs validation,
    * executes actions, manages errors.
   **/
   var Manager = {
      
      errors         : [],
      validators     : {},
      
      addError       : function () {
         this.errors.push( null );
      },
      
      removeError    : function () {
         this.errors.pop();
      },
      
      hasErrors      : function () {
         return !! this.errors.length;
      },
      
      canSubmitForm  : function () {
         return ! this.hasErrors();
      },
      
      defineValidator: function ( operator, validator ) {
         this.validators[ operator ] = validator;
      },
      
      getValidator   : function ( operator ) {
         return this.validators[ operator ];
      },
      
      validateRule   : function ( value, operator, possibilities ) {
         var return_value = false;
         for ( var i=0, l=possibilities.length; i<l; i += 1 ) {
            switch ( operator ) {
            
            case '==' :
            case 'IN' :
               return_value = value == possibilities[i];
               break;
            
            case '!=' :
               return_value = value != possibilities[i];
               break;
            
            case 'NOT IN' :
               if ( value === possibilities[i] ) {
                  return false;
               }
               break;
            
            case '>' :
               return_value = value > possibilities[i];
               break;
            
            case '<' :
               return_value = value < possibilities[i];
               break;
            
            case '>=' :
               return_value = value >= possibilities[i];
               break;
            
            case '<=' :
               return_value = value <= possibilities[i];
               break;
            
            }
            if ( return_value ) {
               return true;
            }
         }
         return operator === 'NOT IN' && !return_value;
      },
      
      validateLike   : function ( value, operator, possibilities ) {
         var regexps = [];
         for ( var i=0, l=possibilities.length; i<l; i += 1 ) {
            // to perform a `like` comparison we create a RegExp
            regexps.push(
               new RegExp(
                  (/^%/.test( possibilities[i] ) ? '' : '^') +
                        possibilities[i] +
                        (/%$/.test( possibilities[i] ) ? '' : '$'),
                  ''
               )
            );
         }
         operator = ({
            'LIKE'      : 'REGEX',
            'NOT LIKE'  : 'REGEX NEGATIVE'
         })[ operator ];
         return this.validateRegex( value, operator, regexps );
      },
      
      validateRegex  : function ( value, operator, possibilities ) {
         for ( var i=0, l=possibilities.length; i<l; i += 1 ) {
            var regexp = possibilities[i];
            // convert all other data types to RegExp
            if ( ! regexp || ! regexp.constructor || regexp.constructor !== RegExp ) {
               regexp = new RegExp( regexp, '' );
            }
            if ( regexp.test( value ) === ( operator === 'REGEX' ) ) {
               return true;
            }
         }
         return false;
      },
      
      validateBetween : function ( value, operator, possibilities ) {
         switch ( operator ) {
         
         case 'BETWEEN' :
            return value >= possibilities[0] && value <= possibilities[1];
         
         case 'NOT BETWEEN' :
            return value < possibilities[0] || value > possibilities[1];
         
         }
         return false;
      },
      
      validate       : function ( elem, options ) {
         var groups     = options.conditions;
         var validates  = false;
         for ( var i=0, l=groups.length; i<l; i += 1 ) {
            // groups will be OR-ed
            var group = groups[i];
            for ( var j=0, jl=group.rules.length; j<jl; j += 1 ) {
               // rules will be AND-ed
               var rule       = group.rules[j];
               var property   = rule[0];
               var value      = property == 'value' ? elem[0].value : elem[0].value.length;
               var operator   = rule[1] == '=' ? '==' : rule[1];
               if ( ! this.getValidator( operator )( value, operator, this.splat( rule[2] ) ) ) {
                  break;
               }
               validates = true;
            }
            this.doActions( group.actions || [], elem, options, validates );
         }
         return validates;
      },
      
      doActions : function ( actions, elem, options, validates ) {
         for ( var i=0, l=actions.length; i<l; i += 1 ) {
            this.doAction.apply( this, [ actions[i] ].concat( Array.prototype.slice.call( arguments, 1 ) ) );
         }
      },
      
      doAction : function ( action, elem, options, validates ) {
         action.options = action.options || {};
         
         var classname = options.messages_class;
         switch ( action.name ) {
         
         case 'hide' :
            (action.options.elements || $())[ validates ? 'show' : 'hide' ]();
            break;
         
         case 'show' :
            (action.options.elements || $())[ validates ? 'hide' : 'show' ]();
            break;
         
         case 'error' :
            classname = options.error_messages_class;
            this[ validates ? 'removeError' : 'addError' ]();
         case 'info' :
            var message_elem = elem.data('jq-conditions-message');
            if ( message_elem ) {
               message_elem.remove();
               elem.data('jq-conditions-message', null);
            }
            if ( ! validates ) {
               message_elem = $([
                  '<span class="', classname, '">', action.options.message || '', '</span>'
               ].join(''));
               this.inject(
                  message_elem,
                  action.options.wrapper,
                  action.options.position,
                  elem
               );
               elem.data('jq-conditions-message', message_elem);
            }
            break;
         
         }
      },
      
      inject : function ( elem, wrapper, where, input ) {
         if ( ['top', 'bottom'].indexOf( where ) == -1 && wrapper ) {
            where = 'bottom';
         }
         if ( ['before', 'after'].indexOf( where ) == -1 && ! wrapper ) {
            where = 'after';
         }
         var methods = {
            top      : 'prepend',
            bottom   : 'append',
            before   : 'before',
            after    : 'after'
         };
         ( wrapper || input )[ methods[ where ] ]( elem );
      },
      
      /**
       * Accepts mixed type and returns Array. If argument is Array, it returns it.
       *
       * @param mixed (Mixed)
       *
       * @returns Array
      **/
      splat : function ( mixed ) {
         if ( mixed.constructor === Array ) {
            return mixed;
         }
         return [ mixed ];
      }
      
   };
   
   // basic validators
   Manager.validators = {
      '=='              : Manager.validateRule.bind( Manager ),
      '!='              : Manager.validateRule.bind( Manager ),
      '>='              : Manager.validateRule.bind( Manager ),
      '<='              : Manager.validateRule.bind( Manager ),
      '>'               : Manager.validateRule.bind( Manager ),
      '<'               : Manager.validateRule.bind( Manager ),
      'IN'              : Manager.validateRule.bind( Manager ),
      'NOT IN'          : Manager.validateRule.bind( Manager ),
      'LIKE'            : Manager.validateLike.bind( Manager ),
      'NOT LIKE'        : Manager.validateLike.bind( Manager ),
      'REGEX'           : Manager.validateRegex.bind( Manager ),
      'REGEX NEGATIVE'  : Manager.validateRegex.bind( Manager ),
      'BETWEEN'         : Manager.validateBetween.bind( Manager ),
      'NOT BETWEEN'     : Manager.validateBetween.bind( Manager )
   };
   
   $.fn.conditions = function ( options ) {
      if ( ! options || ! options.conditions || ! options.conditions.length ) {
         return this;
      }
      
      // set options
      options = $.extend({
         messages_class       : 'jq-conditions-message',
         error_messages_class : 'jq-conditions-error'
      }, options);
      
      this.each( function () {
         var elem = $(this);
         // add onsubmit handler to the form
         var form = elem.parent('form');
         if ( form && ! form.data('conditions-onsubmit') ) {   // make sure we add only 1 handler per form
            var handler = Manager.canSubmitForm.bind( Manager );
            form.on( 'submit', handler );
            form.data( 'conditions-onsubmit', handler );
         }
         
         // add listener to validate value/length change
         elem.on( elem.attr('type') == 'checkbox' ? 'click' : 'change', Manager.validate.bind( Manager, elem, options ) );
      });
      
      return this;
   }
   
   // public method to define custom validator
   $.fn.conditions.defineValidator = Manager.defineValidator.bind( Manager );
   
})( jQuery );

/*------------------------------------------------------------------- Helpers */

/**
 * Function.prototype.bind
 *
 * Changes context (value of `this`) of a function
 *
 * @param context (Object) The value of `this`s
 *
 * @returns Function
**/
if ( ! Function.prototype.bind ) {
   // change context `this` of a function
   Function.prototype.bind = function ( context ) {
      var fn   = arguments.callee;
      var args = Array.prototype.slice.call( arguments, 1 );
      return function () {
         return fn.apply( context, args.concat( arguments ) );
      }
   }
}