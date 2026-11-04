<?php

it('merges its config', fn () => expect(config('lunar.product-packs.enabled'))->toBeTrue());
