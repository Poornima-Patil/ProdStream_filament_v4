<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('User details')
                ->hiddenLabel()
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('View User')
                        ->hiddenLabel()
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (! $record) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Users Found</div>';
                            }

                            $firstName = $record->first_name;
                            $lastName = $record->last_name;
                            $email = $record->email;
                            $Emp_id = $record->emp_id;
                            $Roles = implode(', ', $record->getRoleNames()->toArray());
                            $Department = $record->department && $record->department->name ? $record->department->name : '';

                            return new HtmlString('
                                <!-- Large screen table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">First Name</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Last Name</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Email</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Emp ID</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Roles</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Department</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($firstName).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($lastName).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($email).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Emp_id).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Roles).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Department).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        User details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">First Name: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($firstName).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Last Name: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($lastName).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Email: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($email).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Emp ID: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Emp_id).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Roles: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Roles).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Department: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Department).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),
        ]);
    }
}
