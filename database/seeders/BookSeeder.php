<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * 書籍データを11件投入する。登録者はすべて User::first()（山田太郎）。
     *
     * ISBN 重複を防ぐため firstOrCreate、ジャンル紐付けは genres()->sync() を使用する。
     */
    public function run(): void
    {
        $owner = User::first();
        $genreIds = Genre::pluck('id', 'name');

        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'genres' => ['小説'],
                'description' => '英語教師・珍野苦沙弥の家に飼われる猫の視点から、明治の知識人たちの滑稽な日常を風刺的に描いた長編小説。',
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'genres' => ['ビジネス', '自己啓発'],
                'description' => '人間関係の原則を豊富な逸話とともに説いた自己啓発書の古典。相手の立場に立つことの大切さを繰り返し説く。',
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'genres' => ['技術書'],
                'description' => '読みやすいコードを書くための具体的なテクニックをまとめた、プログラマ必読の一冊。',
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'genres' => ['ビジネス', '自己啓発'],
                'description' => '人格を磨き主体的に生きるための7つの習慣を体系的に解説した世界的ベストセラー。',
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'genres' => ['小説'],
                'description' => '正義感の強い江戸っ子の新任教師が、四国の中学校で繰り広げる痛快な騒動を描いた青春小説。',
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'genres' => ['歴史', '科学'],
                'description' => '認知革命・農業革命・科学革命を軸に、ホモ・サピエンスの歩みを大胆に描き出した人類史。',
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'genres' => ['技術書'],
                'description' => '保守しやすいクリーンなコードとは何かを、実例を交えて論じたソフトウェア開発の名著。',
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'genres' => ['自己啓発'],
                'description' => 'アドラー心理学を哲人と青年の対話形式で解説し、対人関係の悩みから自由になる道を示す。',
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'genres' => ['小説'],
                'description' => '売れない若手芸人の日々と芸への葛藤を、先輩芸人との交流を通して描いた芥川賞受賞作。',
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'genres' => ['ビジネス', '科学'],
                'description' => 'データに基づき世界を正しく見るための10の思い込みと、その克服法を解説する。',
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_date' => '2007-01-18',
                'genres' => ['ビジネス', '歴史'],
                'description' => '海上輸送用コンテナの発明が世界経済とグローバル化に与えた影響を追ったノンフィクション。',
            ],
        ];

        foreach ($books as $index => $data) {
            $book = Book::firstOrCreate(
                ['isbn' => $data['isbn']],
                [
                    'user_id' => $owner->id,
                    'title' => $data['title'],
                    'author' => $data['author'],
                    'published_date' => $data['published_date'],
                    'description' => $data['description'],
                    'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text='.($index + 1),
                ],
            );

            $book->genres()->sync(
                collect($data['genres'])->map(fn (string $name) => $genreIds[$name])->all()
            );
        }
    }
}
