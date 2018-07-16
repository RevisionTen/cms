"use strict";

import fontawesome from '@fortawesome/fontawesome'
import regular from '@fortawesome/fontawesome-free-regular'
import brands from '@fortawesome/fontawesome-free-brands'
import solid from '@fortawesome/fontawesome-free-solid'

fontawesome.config = {
    searchPseudoElements: true
}

fontawesome.library.add(regular, solid)

fontawesome.dom.i2svg()
