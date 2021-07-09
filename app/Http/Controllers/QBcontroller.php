<?php
namespace App\Http\Controllers;
use App\User;
use App\QuestionBank;
use App\Http\Controllers\Questioncontroller;
use Illuminate\Http\Request;
use App\Http\Controllers\AnswerController;
use PhpOffice\PhpSpreadsheet;
use Validator;
use Request;
use App\Question;
use App\Answer;
use DB;

class QBcontroller extends Controller
{
    protected $guarded =[];

    public function __construct() 
    {
        $this->middleware('auth'); 
    }

    public function homeView()
    {
        return view('questionsbank/QBhome');
    }

    public function createQBView()
    {
        return view('questionsbank/createQB');
    }

    public function addQuestionToQBView($QB_id)
    {
        return view('questionsbank/addQuestionToQB',['id'=>$QB_id]);
    }

    public function createQB()
    {
        
        if(auth()->user()->role == 1 )
        {
            $data = request()->validate([ 
                'QBfile' => 'mimes:xls,xlsx,csv',
                'title' => 'required',
                'sub' => 'required',
            ]);
            $QuestionBankData = ['title' => $data['title'] , 'instructor_id' => auth()->user()->id , 'subject_id' => $data['sub']];
            $newQuestionBank = QuestionBank::create($QuestionBankData); // creating the new question bank
            $path = $data['QBfile']->getRealPath();
            $reader= \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $excel_Obj = $reader->load($path);
            $worksheet=$excel_Obj->getActiveSheet();
            $lastRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestDataColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); 
            
            $Questions = array(); // total questions
                     
            for($row=2;$row<=$lastRow ;$row++){
                $QuestionObj = new Question();
                $QuestionFromDataBase;
                for($col='A';$col<=$highestColumn ;$col++)
                {
                    $Answers = array(); // total answers
                    $cellValue = $worksheet->getCell($col . $row)->getValue();
                    if($col == 'A') // Question
                    {
                        $QuestionObj['content'] = $cellValue;
                    }
                    else if ($col == 'B') // Type
                    {
                        $QuestionObj['type'] = $cellValue;
                    }
                    else if ($col == 'C') // Chapter
                    {
                        $QuestionObj->chapter = $cellValue;
                    }
                    else if ($col == 'D') // parent id
                    {
                        $QuestionObj['parent_id'] = $cellValue;
                        $insertQuestion = ['content' => $QuestionObj['content'] , 'type' => $QuestionObj['type'] , 'chapter' => $QuestionObj['chapter'] , 'parent_id' => $QuestionObj['parent_id'] , 'question_bank_id' => $newQuestionBank['id']];
                        $QuestionFromDataBase = Question::create($insertQuestion); 
                    }
                    else if ($col == 'E') // Correct Answer - the question ID is missing :)
                    {
                        
                        if($QuestionObj['type'] == "Essay")
                        {
                            $insertAnswer = ['content' => $cellValue , 'is_correct' => 1 , 'question_id' => $QuestionFromDataBase->id];
                            $dummy = Answer::create($insertAnswer);
                        }
                        else{
                            $answersArray = explode("~" , $cellValue); // cutting string on ~ char
                            for($ans = 0; $ans < count($answersArray) ; $ans++) 
                            {
                                $insertAnswer = ['content' => $answersArray[$ans] , 'is_correct' => 1 , 'question_id' => $QuestionFromDataBase->id];
                                $dummy = Answer::create($insertAnswer);
                            }
                        }
                    }
                    else if ($col == 'F') // Wrong Answer -  the question ID is missing :)
                    {
                        if($QuestionObj['type']!= "Essay")
                        {
                            $answersArray = explode("~" , $cellValue); // cutting string on ~ char
                            for($ans = 0; $ans < count($answersArray) ; $ans++)
                            {
                                $insertAnswer = ['content' => $answersArray[$ans] , 'is_correct' => 0 , 'question_id' => $QuestionFromDataBase->id];
                                $dummy = Answer::create($insertAnswer);
                            }
                        }
                    }
                }
            }
        }
        else
        {
            return view('errorPages/accessDenied');
        }
        return redirect('/QB/home');
    }
    public function destroy($QuestionBankID)
    {
        $QBObj = QuestionBank::find($QuestionBankID);
        $Q = new Questioncontroller();
        $A = new AnswerController();
        $Questions = Question::where('question_bank_id' , $QuestionBankID)->get(); // getting all questions
        $Answers = array();
        for($i=0 ; $i < count($Questions) ; $i++) // getting all answers for the above questions
        {
            $answer = Answer::where('question_id' , $Questions[$i]->id)->get();
            array_push($Answers , $answer);
        }
        $A->delete($Answers);
        $Q->delete($Questions);
        $QBObj = QuestionBank::find($QuestionBankID)->delete();
        return redirect('/QB/home');
    }

    public function addQuestionToQB($QuestionBankID)
    {
        if(auth()->user()->role == 1 )
        {
            $data = request::all();
            $validatedData = Validator::make($data, [
                'chapter' => 'required',
                'type' => 'required',
                'Qcontent' => 'required',
                'answer1' => 'required',
            ]);
            if (!$validatedData->fails()) 
            {
                for ($i = 1; $i <= count($data) - 5 ; $i++) 
                {   
                    if( array_key_exists( "answer".$i ,$data ) )
                    {
                        $answersData['answer'.$i] = $data['answer'.$i];
                        if( array_key_exists( "ch".$i ,$data ) )
                        {
                            $answersData["ch".$i] = $data['ch'.$i];
                        }
                        else
                        {
                            $answersData["ch".$i] = "false";
                        }
                    }
                }   
                
                $Qdata = [ 'content'=>$data['Qcontent'],'type'=>$data['type'],'chapter'=> $data['chapter'],'parent_id'=> $data['parent'] , 'question_bank_id'=> $QuestionBankID ];
                $QC = new Questioncontroller();
                $question_obj = $QC->store($Qdata);
                
                $QC = new AnswerController();
                for ($i = 1; $i <= count($answersData) / 2 ; $i++) 
                {   
                    $temp['content'] = $answersData['answer'.$i];
                    $temp['is_correct'] = $answersData["ch".$i];
                    $temp['question_id'] = $question_obj['id'] ;
                    $QC->store($temp);
                } 
            }
            else
            {
                return response($validatedData->messages(), 200);
            }                    
        }
        else
        {
            return view('errorPages/accessDenied');
        }
        return $this->addQuestionToQBView($QuestionBankID); 
    }

        

}
