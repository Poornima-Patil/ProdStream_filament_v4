<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;

use App\Models\User;
use Filament\Infolists\Components\Progress;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('View User')
                ->hiddenLabel()
                ->collapsible()
                ->schema([
                    TextEntry::make('View User')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (!$record) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Users Found</div>';
                            }
                            $firstName = $record->first_name;
                            $lastName = $record->last_name;
                            $email = $record->email;
                            $Emp_id = $record->emp_id;
                            $Roles = implode(', ', $record->getRoleNames()->toArray());
                             $Department = $record->department && $record->department->name ? $record->department->name : '';

                            return new \Illuminate\Support\HtmlString('
                                <div class="overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">First Name</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Last Name</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Email</th>
                                                 <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Emp ID</th>
                                                  <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Roles</th>
                                                   <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Department</th>
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
                            ');
                        })->html(),
                ]),
        ]);
    }
}


