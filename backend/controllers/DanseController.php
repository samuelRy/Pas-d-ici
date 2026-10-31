<?php
declare(strict_types=1);

final class DanseController
{
    /** GET /api/danses */
    public function index(): void
    {
        Response::ok(Danse::all());
    }
}
