<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018, 2019  Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Eveapi\Jobs\Contacts\Character;

use Seat\Eveapi\Jobs\EsiBase;
use Seat\Eveapi\Models\Contacts\CharacterContact;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Contacts.
 * @package Seat\Eveapi\Jobs\Contacts\Character
 */
class Contacts extends EsiBase
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/contacts/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-characters.read_contacts.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'contacts'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $known_contact_ids;

    /**
     * Contacts constructor.
     *
     * @param \Seat\Eveapi\Models\RefreshToken|null $token
     */
    public function __construct(RefreshToken $token = null)
    {

        $this->known_contact_ids = collect();

        parent::__construct($token);
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     * @throws \Throwable
     */
    public function handle()
    {

        if (! $this->preflighted()) return;

        while (true) {

            $contacts = $this->retrieve([
                'character_id' => $this->getCharacterId(),
            ]);

            if ($contacts->isCachedLoad()) return;

            collect($contacts)->each(function ($contact) {

                CharacterContact::firstOrNew([
                    'character_id' => $this->getCharacterId(),
                    'contact_id'   => $contact->contact_id,
                ])->fill([
                    'standing'     => $contact->standing,
                    'contact_type' => $contact->contact_type,
                    'is_watched'   => $contact->is_watched ?? false,
                    'is_blocked'   => $contact->is_blocked ?? false,
                    'label_ids'    => $contact->label_ids ?? null,
                ])->save();
            });

            $this->known_contact_ids->push(collect($contacts)
                ->pluck('contact_id')->flatten()->all());

            if (! $this->nextPage($contacts->pages))
                break;
        }

        // Cleanup old contacts
        CharacterContact::where('character_id', $this->getCharacterId())
            ->whereNotIn('contact_id', $this->known_contact_ids->flatten()->all())
            ->delete();
    }
}
