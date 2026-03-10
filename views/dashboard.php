<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
$csrfToken = csrfToken();
$userName = sanitize($_SESSION['user_name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Law Firm CRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-100" x-data="crmApp()" x-init="fetchLeads()">

    <!-- Nav -->
    <nav class="bg-blue-800 text-white p-4 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Law Firm CRM</h1>
            <div class="flex items-center gap-4">
                <span class="text-blue-200 text-sm">Welcome, <?= $userName ?></span>
                <button @click="addLead()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-sm">+ New Lead</button>
                <button @click="logout()" class="bg-blue-900 hover:bg-blue-950 px-4 py-2 rounded text-sm">Logout</button>
            </div>
        </div>
    </nav>

    <!-- Stats -->
    <div class="container mx-auto mt-6 grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Total Leads</div>
            <div class="text-2xl font-bold text-gray-800" x-text="meta.total || 0"></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">New</div>
            <div class="text-2xl font-bold text-green-600" x-text="leads.filter(l => l.status === 'New').length"></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">In Progress</div>
            <div class="text-2xl font-bold text-yellow-600" x-text="leads.filter(l => l.status === 'In Progress').length"></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Avg Score</div>
            <div class="text-2xl font-bold text-blue-600" x-text="avgScore"></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Sources</div>
            <div class="text-2xl font-bold text-purple-600" x-text="uniqueSources"></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="container mx-auto mt-4 flex flex-wrap gap-3">
        <input type="text" x-model="filters.search" @input.debounce.300ms="fetchLeads()"
               placeholder="Search leads..."
               class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select x-model="filters.status" @change="fetchLeads()"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm">
            <option value="">All Statuses</option>
            <option value="New">New</option>
            <option value="In Progress">In Progress</option>
            <option value="Closed">Closed</option>
        </select>
        <select x-model="filters.practice_area" @change="fetchLeads()"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm">
            <option value="">All Practice Areas</option>
            <option value="Criminal Defense">Criminal Defense</option>
            <option value="Personal Injury">Personal Injury</option>
            <option value="Family Law">Family Law</option>
            <option value="Estate Planning">Estate Planning</option>
            <option value="Business Law">Business Law</option>
            <option value="Real Estate Law">Real Estate Law</option>
        </select>
        <select x-model="filters.source" @change="fetchLeads()"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm">
            <option value="">All Sources</option>
            <option value="direct">Direct</option>
            <option value="website">Website</option>
            <option value="referral">Referral</option>
            <option value="guide-download">Guide Download</option>
            <option value="quiz">Quiz</option>
        </select>
        <select x-model="filters.state" @change="fetchLeads()"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm">
            <option value="">All States</option>
            <option value="TX">Texas</option>
        </select>
    </div>

    <!-- Leads Table -->
    <div class="container mx-auto mt-4 mb-8">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Practice Area</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="lead in leads" :key="lead.id">
                            <tr class="hover:bg-gray-50 cursor-pointer" @click="showDetail(lead)">
                                <td class="px-3 py-3 text-sm font-medium" x-text="lead.name"></td>
                                <td class="px-3 py-3 text-sm text-gray-600" x-text="lead.email"></td>
                                <td class="px-3 py-3 text-sm text-gray-600" x-text="lead.phone"></td>
                                <td class="px-3 py-3 text-sm" x-text="lead.practice_area"></td>
                                <td class="px-3 py-3 text-sm">
                                    <span x-text="lead.source || 'direct'"
                                          class="px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800"></span>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600">
                                    <span x-text="[lead.city, lead.state].filter(Boolean).join(', ') || '-'"></span>
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    <span x-text="lead.status"
                                          :class="{
                                              'bg-green-100 text-green-800': lead.status === 'New',
                                              'bg-yellow-100 text-yellow-800': lead.status === 'In Progress',
                                              'bg-red-100 text-red-800': lead.status === 'Closed'
                                          }"
                                          class="px-2 py-1 rounded-full text-xs font-medium"></span>
                                </td>
                                <td class="px-3 py-3 text-sm" x-text="lead.score"></td>
                                <td class="px-3 py-3 text-sm" @click.stop>
                                    <button @click="editLead(lead)" class="text-blue-600 hover:text-blue-800 mr-2">Edit</button>
                                    <button @click="deleteLead(lead.id)" class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t bg-gray-50 flex justify-between items-center text-sm text-gray-600">
                <span>Showing <span x-text="leads.length"></span> of <span x-text="meta.total"></span> leads</span>
                <div class="flex gap-2">
                    <button @click="prevPage()" :disabled="meta.page <= 1"
                            :class="meta.page <= 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                            class="px-3 py-1 border rounded">Prev</button>
                    <span class="px-3 py-1">Page <span x-text="meta.page"></span> of <span x-text="meta.pages"></span></span>
                    <button @click="nextPage()" :disabled="meta.page >= meta.pages"
                            :class="meta.page >= meta.pages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                            class="px-3 py-1 border rounded">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Panel -->
    <div x-show="showDetailPanel" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex justify-end z-40" @click.self="showDetailPanel = false">
        <div class="bg-white w-full max-w-md h-full overflow-y-auto shadow-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold" x-text="detailLead.name"></h2>
                <button @click="showDetailPanel = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
            <div class="space-y-3 text-sm">
                <div><span class="text-gray-500">Email:</span> <span x-text="detailLead.email"></span></div>
                <div><span class="text-gray-500">Phone:</span> <span x-text="detailLead.phone || '-'"></span></div>
                <div><span class="text-gray-500">Practice Area:</span> <span x-text="detailLead.practice_area"></span></div>
                <div><span class="text-gray-500">Status:</span> <span x-text="detailLead.status"></span></div>
                <div><span class="text-gray-500">Score:</span> <span x-text="detailLead.score"></span></div>
                <hr>
                <div><span class="text-gray-500">Source:</span> <span x-text="detailLead.source || 'direct'"></span></div>
                <div><span class="text-gray-500">City:</span> <span x-text="detailLead.city || '-'"></span></div>
                <div><span class="text-gray-500">State:</span> <span x-text="detailLead.state || '-'"></span></div>
                <div x-show="detailLead.notes">
                    <div class="text-gray-500 mb-1">Notes:</div>
                    <div class="bg-gray-50 p-3 rounded text-sm" x-text="detailLead.notes"></div>
                </div>
                <hr>
                <div class="text-xs text-gray-400">
                    <div x-show="detailLead.utm_source">UTM Source: <span x-text="detailLead.utm_source"></span></div>
                    <div x-show="detailLead.utm_medium">UTM Medium: <span x-text="detailLead.utm_medium"></span></div>
                    <div x-show="detailLead.utm_campaign">UTM Campaign: <span x-text="detailLead.utm_campaign"></span></div>
                    <div>Created: <span x-text="detailLead.created_at"></span></div>
                </div>
            </div>
            <div class="mt-6 flex gap-2">
                <button @click="editLead(detailLead); showDetailPanel = false"
                        class="px-4 py-2 text-sm bg-blue-700 hover:bg-blue-800 text-white rounded-md">Edit</button>
                <button @click="deleteLead(detailLead.id); showDetailPanel = false"
                        class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-md">Delete</button>
            </div>
        </div>
    </div>

    <!-- Edit/Add Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto" @click.outside="showModal = false">
            <h2 class="text-xl font-bold mb-4" x-text="currentLead.id ? 'Edit Lead' : 'Add New Lead'"></h2>
            <div x-show="formError" class="bg-red-100 text-red-700 p-2 rounded mb-3 text-sm" x-text="formError"></div>
            <form @submit.prevent="saveLead()">
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" x-model="currentLead.name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" x-model="currentLead.email" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" x-model="currentLead.phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Practice Area</label>
                        <select x-model="currentLead.practice_area" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="">Select...</option>
                            <option value="Criminal Defense">Criminal Defense</option>
                            <option value="Personal Injury">Personal Injury</option>
                            <option value="Family Law">Family Law</option>
                            <option value="Estate Planning">Estate Planning</option>
                            <option value="Business Law">Business Law</option>
                            <option value="Real Estate Law">Real Estate Law</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select x-model="currentLead.status" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="New">New</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Score (0-100)</label>
                        <input type="number" x-model="currentLead.score" min="0" max="100" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                        <input type="text" x-model="currentLead.source" placeholder="direct"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" x-model="currentLead.city" placeholder="Houston"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" x-model="currentLead.state" placeholder="TX" maxlength="2"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea x-model="currentLead.notes" rows="3" placeholder="Internal notes..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-sm bg-gray-200 hover:bg-gray-300 rounded-md">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-blue-700 hover:bg-blue-800 text-white rounded-md">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/public/js/app.js"></script>
    <script>
        // Pass CSRF token from PHP session to JS
        window.CSRF_TOKEN = '<?= $csrfToken ?>';
    </script>
</body>
</html>
