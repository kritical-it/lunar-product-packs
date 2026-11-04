<?php

namespace Gongarce\ProductPacks\Events;

enum PackChangeReason: string
{
    /** Title or status. */
    case Saved = 'saved';

    case Deleted = 'deleted';

    /** A product added, removed or changed (quantity, «show on its page», position). */
    case ItemsChanged = 'items_changed';

    /** A rule of an option, a link, a pair or one of its values. */
    case OptionsChanged = 'options_changed';
}
