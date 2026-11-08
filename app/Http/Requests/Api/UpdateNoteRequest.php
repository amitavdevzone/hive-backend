<?php

namespace App\Http\Requests\Api;

/**
 * Same rules as creation: all fields, and at least one tag, are mandatory.
 */
class UpdateNoteRequest extends StoreNoteRequest {}
