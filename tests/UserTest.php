<?php

namespace Sleuren\Tests;

use Sleuren\Sleuren;
use Sleuren\Tests\Mocks\SleurenClient;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserTest extends TestCase
{
    /** @var Mocks\SleurenClient */
    protected $client;

    /** @var Sleuren */
    protected $sleuren;

    public function setUp(): void
    {
        parent::setUp();

        $this->sleuren = new Sleuren($this->client = new SleurenClient(
            'project_key'
        ));
    }

    /** @test */
    public function it_return_custom_user()
    {
        $this->actingAs((new CustomerUser())->forceFill([
            'id' => 1,
            'username' => 'username',
            'password' => 'password',
            'email' => 'email',
        ]));

        $this->assertSame(['id' => 1, 'username' => 'username', 'password' => 'password', 'email' => 'email'], $this->sleuren->getUser());
    }

    /** @test */
    public function it_return_custom_user_with_to_sleuren()
    {
        $this->actingAs((new CustomerUserWithToSleuren())->forceFill([
            'id' => 1,
            'username' => 'username',
            'password' => 'password',
            'email' => 'email',
        ]));

        $this->assertSame(['username' => 'username', 'email' => 'email'], $this->sleuren->getUser());
    }

    /** @test */
    public function it_returns_nothing_for_ghost()
    {
        $this->assertSame(null, $this->sleuren->getUser());
    }
}

class CustomerUser extends AuthUser
{
    protected $guarded = [];
}

class CustomerUserWithToSleuren extends CustomerUser implements \Sleuren\Concerns\Sleurenable
{
    public function toSleuren()
    {
        return [
            'username' => $this->username,
            'email' => $this->email,
        ];
    }
}
