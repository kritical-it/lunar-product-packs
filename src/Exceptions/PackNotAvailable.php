<?php

namespace KriticalIt\ProductPacks\Exceptions;

use InvalidArgumentException;

/** A pack that can't be added to a cart: it is not published. */
class PackNotAvailable extends InvalidArgumentException {}
